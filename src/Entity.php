<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema;

use Garden\Utils\ArrayUtils;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use ReflectionClass;
use ReflectionEnum;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionUnionType;

/**
 * Base class for strongly-typed data objects that automatically generate schemas from properties.
 *
 * Entities implement ArrayAccess for convenience. Note that offsetSet() and direct property
 * assignment do NOT perform validation. Use Entity::from() for validated construction, or
 * call $entity->validate() after modifications to verify the entity's current state.
 *
 * Entities support multiple schema variants for different API use cases:
 * - Full: Complete entity with all properties (default)
 * - Fragment: Reduced version for lists, omitting large fields
 * - Mutable: Fields that can be modified by consumers
 * - Create: Includes create-only fields
 * - Internal: For system/internal use
 *
 * Use ExcludeFromVariant and IncludeOnlyInVariant attributes to control which
 * properties appear in each variant. You can also define custom variant enums.
 *
 * The serializationVariant controls which properties are included in toArray() and JSON output.
 */
abstract class Entity implements EntityInterface, \ArrayAccess, \JsonSerializable {
    use EntityTrait;

    /**
     * Build and return the schema for this entity using reflection.
     *
     * @param \BackedEnum|null $variant The schema variant to generate. Defaults to SchemaVariant::Full.
     *                                   You can use SchemaVariant or any custom BackedEnum.
     * @return Schema
     */
    public static function getSchema(?\BackedEnum $variant = null): Schema {
        $variant ??= SchemaVariant::Full;
        $class = static::class;

        return EntitySchemaCache::getOrCreate($class, $variant, function () use ($class, $variant) {
            return self::buildSchema($class, $variant);
        });
    }

    /**
     * Build a schema for the given class and variant.
     *
     * @param class-string $class The entity class to build a schema for.
     * @param \BackedEnum $variant The schema variant to build.
     * @return Schema
     */
    protected static function buildSchema(string $class, \BackedEnum $variant): Schema {
        $reflection = new ReflectionClass($class);
        // Get all properties, not just public - non-public properties with PropertySchema will be schema-only
        $properties = $reflection->getProperties();
        $properties = self::sortPropertiesBySchemaOrder($properties);
        $schemaProperties = [];
        $required = [];
        $includedProperties = [];

        foreach ($properties as $property) {
            if ($property->isStatic()) {
                continue;
            }

            if (self::isPropertyExcluded($property)) {
                continue;
            }

            // For non-public properties, only include if they have a PropertySchema attribute.
            // This allows schema-only properties that appear in getSchema() but won't be encoded/decoded
            // (since we can't set private/protected properties from a parent class).
            $isSchemaOnly = false;
            if (!$property->isPublic()) {
                $hasPropertySchema = !empty($property->getAttributes(PropertySchemaInterface::class, \ReflectionAttribute::IS_INSTANCEOF));
                if (!$hasPropertySchema) {
                    continue;
                }
                $isSchemaOnly = true;
            }

            // Check if property should be included in this variant
            if (!self::isPropertyInVariant($property, $variant)) {
                continue;
            }

            $propertyName = $property->getName();
            $propertySchema = self::buildPropertySchema($property, $class, $variant);

            // Mark schema-only properties so toArray() can skip them
            if ($isSchemaOnly) {
                $propertySchema['x-schema-only'] = true;
            }

            $schemaProperties[$propertyName] = $propertySchema;
            $includedProperties[] = $property;

            // Schema-only properties can't be set from outside, so they should never be required
            if (!$isSchemaOnly && self::isPropertyRequired($property)) {
                $required[] = $propertyName;
            }
        }

        $schemaArray = [
            'type' => 'object',
            'properties' => $schemaProperties,
        ];
        if (!empty($required)) {
            $schemaArray['required'] = $required;
        }

        $schema = new Schema($schemaArray);

        // Collect alt names and add filter for property name mapping
        // Only include alt names for properties that are in this variant
        $altNameMappings = self::collectAltNameMappings($includedProperties);
        if (!empty($altNameMappings)) {
            $schema->addFilter('', function ($data) use ($altNameMappings) {
                if (!is_array($data)) {
                    return $data;
                }
                foreach ($altNameMappings as $mainName => $config) {
                    if (!array_key_exists($mainName, $data)) {
                        $altNames = $config['altNames'];
                        $useDotNotation = $config['useDotNotation'];

                        foreach ($altNames as $altName) {
                            // Check if we should use dot notation for nested path lookup
                            if ($useDotNotation && str_contains($altName, '.')) {
                                // Use a unique sentinel to detect if path exists
                                $sentinel = new \stdClass();
                                $value = ArrayUtils::getByPath($altName, $data, $sentinel);
                                if ($value !== $sentinel) {
                                    $data[$mainName] = $value;
                                    break;
                                }
                            } elseif (array_key_exists($altName, $data)) {
                                $data[$mainName] = $data[$altName];
                                unset($data[$altName]);
                                break;
                            }
                        }
                    }
                }
                return $data;
            });
        }

        // Collect sub-property mappings and add filter for nested property construction
        $subPropertyMappings = self::collectSubPropertyMappings($includedProperties);
        if (!empty($subPropertyMappings)) {
            $schema->addFilter('', function ($data) use ($subPropertyMappings) {
                if (!is_array($data)) {
                    return $data;
                }
                foreach ($subPropertyMappings as $targetProperty => $config) {
                    $keys = $config['keys'];
                    $mapping = $config['mapping'];

                    // Initialize target array if it doesn't exist
                    if (!array_key_exists($targetProperty, $data)) {
                        $data[$targetProperty] = [];
                    }

                    // Ensure target is an array we can modify
                    if (!is_array($data[$targetProperty])) {
                        continue;
                    }

                    // Copy flat keys from root data
                    foreach ($keys as $key) {
                        $sentinel = new \stdClass();
                        $value = ArrayUtils::getByPath($key, $data, $sentinel);
                        if ($value !== $sentinel) {
                            ArrayUtils::setByPath($key, $data[$targetProperty], $value);
                        }
                    }

                    // Apply source-to-target mappings
                    foreach ($mapping as $sourcePath => $targetPath) {
                        $sentinel = new \stdClass();
                        $value = ArrayUtils::getByPath($sourcePath, $data, $sentinel);
                        if ($value !== $sentinel) {
                            ArrayUtils::setByPath($targetPath, $data[$targetProperty], $value);
                        }
                    }
                }
                return $data;
            });
        }

        return $schema;
    }

    /**
     * Check if a property should be included in a specific schema variant.
     *
     * The logic is:
     * 1. If IncludeOnlyInVariant is present, only include in those specific variants
     * 2. If ExcludeFromVariant is present, exclude from those specific variants
     * 3. Otherwise, include in all variants (default behavior)
     *
     * @param ReflectionProperty $property The property to check.
     * @param \BackedEnum $variant The variant to check against.
     * @return bool True if the property should be included in the variant.
     */
    private static function isPropertyInVariant(ReflectionProperty $property, \BackedEnum $variant): bool {
        // Check for IncludeOnlyInVariant first (takes precedence)
        $includeOnlyAttrs = $property->getAttributes(IncludeOnlyInVariant::class);
        if (!empty($includeOnlyAttrs)) {
            /** @var IncludeOnlyInVariant $includeOnly */
            $includeOnly = $includeOnlyAttrs[0]->newInstance();
            return $includeOnly->includesVariant($variant);
        }

        // Check for ExcludeFromVariant
        $excludeAttrs = $property->getAttributes(ExcludeFromVariantInterface::class, \ReflectionAttribute::IS_INSTANCEOF);
        foreach ($excludeAttrs as $excludeAttr) {
            /** @var ExcludeFromVariantInterface $exclude */
            $exclude = $excludeAttr->newInstance();
            if ($exclude->excludesVariant($variant)) {
                return false;
            }
        }

        // Default: include in all variants
        return true;
    }

    /**
     * Clear the cached schema for a specific class or all classes.
     *
     * Useful for testing or scenarios where entity definitions may change at runtime.
     *
     * @param class-string|null $class The class to clear cache for, or null to clear all.
     * @param \BackedEnum|null $variant The specific variant to clear, or null to clear all variants for the class.
     */
    public static function invalidateSchemaCache(?string $class = null, ?\BackedEnum $variant = null): void {
        if ($class === null) {
            EntitySchemaCache::invalidateAll();
        } else {
            EntitySchemaCache::invalidate($class, $variant);
        }
    }

    /**
     * Collect alternative name mappings from PropertyAltNames attributes.
     *
     * @param ReflectionProperty[] $properties
     * @return array<string, array{altNames: string[], useDotNotation: bool}> Map of property name to alt name config.
     */
    private static function collectAltNameMappings(array $properties): array {
        $mappings = [];
        foreach ($properties as $property) {
            if ($property->isStatic()) {
                continue;
            }
            if (self::isPropertyExcluded($property)) {
                continue;
            }
            $attributes = $property->getAttributes(PropertyAltNames::class);
            if (!empty($attributes)) {
                /** @var PropertyAltNames $attr */
                $attr = $attributes[0]->newInstance();
                $altNames = $attr->getAltNames();
                if (!empty($altNames)) {
                    $mappings[$property->getName()] = [
                        'altNames' => $altNames,
                        'useDotNotation' => $attr->useDotNotation(),
                    ];
                }
            }
        }
        return $mappings;
    }

    /**
     * Collect sub-property mappings from MapSubProperties attributes.
     *
     * @param ReflectionProperty[] $properties
     * @return array<string, array{keys: string[], mapping: array<string, string>}> Map of property name to mapping config.
     */
    private static function collectSubPropertyMappings(array $properties): array {
        $mappings = [];
        foreach ($properties as $property) {
            if ($property->isStatic()) {
                continue;
            }
            if (self::isPropertyExcluded($property)) {
                continue;
            }
            $attributes = $property->getAttributes(MapSubProperties::class);
            if (!empty($attributes)) {
                /** @var MapSubProperties $attr */
                $attr = $attributes[0]->newInstance();
                $keys = $attr->getKeys();
                $mapping = $attr->getMapping();
                if (!empty($keys) || !empty($mapping)) {
                    $mappings[$property->getName()] = [
                        'keys' => $keys,
                        'mapping' => $mapping,
                    ];
                }
            }
        }
        return $mappings;
    }

    /**
     * Hydrate an entity instance from already-validated data.
     *
     * This method skips validation and directly assigns properties.
     * Use this when the data has already been validated by the schema.
     *
     * @param array $clean The validated data array.
     * @param \BackedEnum|null $variant The schema variant used for validation. Defaults to Full.
     *                                   This is passed to nested entities during hydration.
     * @return static
     */
    public static function fromValidated(array $clean, ?\BackedEnum $variant = null): static {
        $entity = new static();
        $reflection = new ReflectionClass(static::class);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);
        $schemaProperties = static::getSchema($variant)->getSchemaArray()['properties'] ?? [];

        foreach ($properties as $property) {
            if ($property->isStatic()) {
                continue;
            }

            if (self::isPropertyExcluded($property)) {
                continue;
            }

            $name = $property->getName();

            // Handle properties not provided in the input
            if (!array_key_exists($name, $clean)) {
                $type = self::getPropertyType($property);
                if ($type !== null) {
                    // Check if the property type implements EntityDefaultInterface
                    if (!$type->isBuiltin()) {
                        $typeName = $type->getName();
                        if (is_subclass_of($typeName, EntityDefaultInterface::class)) {
                            /** @var EntityDefaultInterface $typeName */
                            $entity->{$name} = $typeName::default();
                            continue;
                        }
                    }

                    // Initialize nullable properties with null if not provided
                    if ($type->allowsNull() && !$property->hasDefaultValue()) {
                        $entity->{$name} = null;
                    }
                }
                continue;
            }

            $value = $clean[$name];
            $type = self::getPropertyType($property);

            if ($type !== null && !$type->isBuiltin()) {
                $typeName = $type->getName();
                // Handle properties typed as the EntityInterface interface itself
                // We can't call fromValidated() on an interface, so only assign existing instances
                if ($typeName === EntityInterface::class) {
                    if ($value === null) {
                        $entity->{$name} = null;
                    } elseif ($value instanceof EntityInterface) {
                        $entity->{$name} = $value;
                    }
                    // Skip assignment for non-EntityInterface values (e.g., arrays)
                    // since we don't know which concrete class to instantiate
                    continue;
                } elseif (is_subclass_of($typeName, EntityInterface::class)) {
                    // Handle nested entities - both Entity subclasses and other EntityInterface implementations
                    if ($value === null) {
                        $entity->{$name} = null;
                        continue;
                    }
                    if (!$value instanceof $typeName) {
                        $value = $typeName::fromValidated($value, $variant);
                    }
                } elseif (is_subclass_of($typeName, \BackedEnum::class)) {
                    // Handle enum conversion if Schema didn't already convert it
                    if (!$value instanceof $typeName && $value !== null) {
                        $value = $typeName::from($value);
                    }
                } elseif ($typeName === \ArrayObject::class || is_subclass_of($typeName, \ArrayObject::class)) {
                    // Convert array to ArrayObject instance
                    if (is_array($value)) {
                        $value = new $typeName($value);
                    }
                } elseif ($typeName === \DateTimeImmutable::class || is_subclass_of($typeName, \DateTimeImmutable::class)) {
                    // Convert to DateTimeImmutable if not already
                    if ($value !== null && !$value instanceof \DateTimeImmutable) {
                        if ($value instanceof \DateTimeInterface) {
                            $value = \DateTimeImmutable::createFromInterface($value);
                        } elseif (is_string($value)) {
                            $value = new \DateTimeImmutable($value);
                        }
                    }
                } elseif ($typeName === UuidInterface::class || is_subclass_of($typeName, UuidInterface::class)) {
                    // Convert to UUID if not already
                    if ($value !== null && !$value instanceof UuidInterface) {
                        if (is_string($value)) {
                            // First check if it's a valid UUID string format (36 chars with dashes, or 32 hex chars)
                            if (Uuid::isValid($value)) {
                                $value = Uuid::fromString($value);
                            } elseif (strlen($value) === 16 && !ctype_print($value)) {
                                // If not a valid string format, try parsing as 16-byte binary
                                // Only accept as bytes if it contains non-printable characters (actual binary data)
                                $value = Uuid::fromBytes($value);
                            }
                        }
                    }
                }
            } elseif (is_array($value)) {
                // Handle arrays of nested entities via PropertySchema attribute
                $propertySchema = $schemaProperties[$name] ?? [];
                $itemsEntityClass = $propertySchema['items']['entityClassName'] ?? null;
                if ($itemsEntityClass && is_subclass_of($itemsEntityClass, self::class)) {
                    $value = array_map(function ($item) use ($itemsEntityClass, $variant) {
                        if ($item instanceof $itemsEntityClass) {
                            return $item;
                        }
                        if (is_array($item)) {
                            return $itemsEntityClass::fromValidated($item, $variant);
                        }
                        return $item;
                    }, $value);
                }
            }

            $entity->{$name} = $value;
        }

        return $entity;
    }

    /**
     * Build a schema array for a property.
     *
     * @param ReflectionProperty $property
     * @param class-string $currentClass The class currently being built (to detect self-reference).
     * @param \BackedEnum $variant The schema variant being generated.
     * @return array
     */
    private static function buildPropertySchema(ReflectionProperty $property, string $currentClass, \BackedEnum $variant): array {
        $schema = [];
        $type = self::getPropertyType($property);

        // Check for NestedVariant attribute to determine which variant to use for nested entities
        $nestedVariant = self::getNestedVariantForProperty($property, $variant);

        if ($type !== null) {
            $typeName = $type->getName();
            if ($type->isBuiltin()) {
                $schemaType = self::mapBuiltinType($typeName);
                if ($schemaType !== null) {
                    $schema['type'] = $schemaType;
                }
            } else {
                if ($typeName === EntityInterface::class) {
                    // Handle properties typed as the EntityInterface interface itself
                    // Since we don't know the concrete type, just mark as generic object
                    $schema['type'] = 'object';
                } elseif (is_subclass_of($typeName, EntityInterface::class)) {
                    // Handle nested entities - both Entity subclasses and other EntityInterface implementations
                    // For self-referencing entities, don't recursively get the full schema
                    // Just mark with entityClassName and let Schema handle it lazily
                    if ($typeName === $currentClass) {
                        $schema['type'] = 'object';
                        $schema['entityClassName'] = $typeName;
                    } else {
                        // Use the resolved nested variant for the child entity's schema
                        $schema = $typeName::getSchema($nestedVariant)->getSchemaArray();
                        $schema['entityClassName'] = $typeName;
                    }

                    // If the entity implements EntityDefaultInterface, include the default in schema
                    if (is_subclass_of($typeName, EntityDefaultInterface::class)) {
                        /** @var class-string<Entity&EntityDefaultInterface> $typeName */
                        $defaultInstance = $typeName::default();
                        $schema['default'] = $defaultInstance->toArray();
                    }
                } elseif (is_subclass_of($typeName, \BackedEnum::class)) {
                    $schema = self::buildEnumSchema($typeName);
                } elseif ($typeName === \ArrayObject::class || is_subclass_of($typeName, \ArrayObject::class)) {
                    $schema['type'] = 'object';
                } elseif ($typeName === \DateTimeImmutable::class || is_subclass_of($typeName, \DateTimeImmutable::class)) {
                    $schema['type'] = 'string';
                    $schema['format'] = 'date-time';
                } elseif ($typeName === UuidInterface::class || is_subclass_of($typeName, UuidInterface::class)) {
                    $schema['type'] = 'string';
                    $schema['format'] = 'uuid';
                } else {
                    throw new \InvalidArgumentException("Unsupported property type {$typeName}.");
                }
            }

            if ($type->allowsNull()) {
                $schema['nullable'] = true;
            }
        }

        if ($property->hasDefaultValue()) {
            $schema['default'] = $property->getDefaultValue();
        }

        $attributeSchema = self::getPropertySchemaAttribute($property);
        if ($attributeSchema !== null) {
            $baseSchema = new Schema($schema);
            $baseSchema->merge($attributeSchema);
            $schema = $baseSchema->getSchemaArray();
        }

        // Store the nested variant in schema metadata if different from parent variant
        // This allows toArray() to read it without reflection on every call
        if ($nestedVariant !== $variant) {
            $schema['x-nested-serialization-variant'] = $nestedVariant;
        }

        return $schema;
    }

    /**
     * Get the variant to use for a nested entity's schema based on NestedVariant attribute.
     *
     * @param ReflectionProperty $property The property to check.
     * @param \BackedEnum $parentVariant The parent entity's schema variant.
     * @return \BackedEnum The resolved variant for the nested entity schema.
     */
    private static function getNestedVariantForProperty(ReflectionProperty $property, \BackedEnum $parentVariant): \BackedEnum {
        $attributes = $property->getAttributes(NestedVariant::class);
        if (empty($attributes)) {
            return $parentVariant;
        }

        /** @var NestedVariant $nestedVariant */
        $nestedVariant = $attributes[0]->newInstance();
        return $nestedVariant->resolveVariant($parentVariant) ?? $parentVariant;
    }

    /**
     * Get the declared property type or throw when unions/intersections are used.
     *
     * @param ReflectionProperty $property
     * @return ReflectionNamedType|null
     */
    private static function getPropertyType(ReflectionProperty $property): ?ReflectionNamedType {
        $type = $property->getType();
        if ($type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType) {
            throw new \InvalidArgumentException("Union and intersection types are not supported for {$property->getName()}.");
        }

        return $type instanceof ReflectionNamedType ? $type : null;
    }

    /**
     * Map builtin PHP types to schema types.
     *
     * @param string $typeName
     * @return string|null
     */
    private static function mapBuiltinType(string $typeName): ?string {
        return match ($typeName) {
            'string' => 'string',
            'int' => 'integer',
            'float' => 'number',
            'array' => 'array',
            'bool' => 'boolean',
            default => null,
        };
    }

    /**
     * Build a schema array for a backed enum.
     *
     * @param class-string<\BackedEnum> $enumClass
     * @return array
     */
    private static function buildEnumSchema(string $enumClass): array {
        $reflection = new ReflectionEnum($enumClass);
        $backingType = $reflection->getBackingType();
        if ($backingType === null) {
            throw new \InvalidArgumentException("Enum {$enumClass} must be backed.");
        }

        $typeName = $backingType->getName();
        $schemaType = $typeName === 'int' ? 'integer' : 'string';

        return [
            'type' => $schemaType,
            'enumClassName' => $enumClass,
        ];
    }

    /**
     * Determine if a property is required.
     *
     * A property is required if it must always be present in validated data.
     * Properties with defaults are still required because the schema will apply the default.
     *
     * @param ReflectionProperty $property
     * @return bool
     */
    private static function isPropertyRequired(ReflectionProperty $property): bool {
        // Check for explicit Required attribute first - this overrides nullability
        $requiredAttributes = $property->getAttributes(Required::class);
        if (!empty($requiredAttributes)) {
            return true;
        }

        $type = self::getPropertyType($property);

        // Properties without a type hint are implicitly mixed/nullable, so optional
        if ($type === null) {
            return false;
        }

        // Nullable properties are optional
        if ($type->allowsNull()) {
            return false;
        }

        // Properties with defaults are still required - the schema will apply the default
        // This ensures they're always present in the output

        return true;
    }

    /**
     * Sort properties by SchemaOrder attribute.
     *
     * Properties with a SchemaOrder attribute come first, sorted by their order
     * value in ascending order. Properties without SchemaOrder retain their original
     * declaration order and appear after all ordered properties.
     *
     * @param ReflectionProperty[] $properties
     * @return ReflectionProperty[]
     */
    private static function sortPropertiesBySchemaOrder(array $properties): array {
        // Separate properties with and without SchemaOrder
        $ordered = [];
        $unordered = [];

        foreach ($properties as $property) {
            $attributes = $property->getAttributes(SchemaOrder::class);
            if (!empty($attributes)) {
                /** @var SchemaOrder $schemaOrder */
                $schemaOrder = $attributes[0]->newInstance();
                $ordered[] = ['property' => $property, 'order' => $schemaOrder->getOrder()];
            } else {
                $unordered[] = $property;
            }
        }

        // Sort the ordered properties by their order value (ascending)
        usort($ordered, fn($a, $b) => $a['order'] <=> $b['order']);

        // Combine: ordered properties first, then unordered in their original order
        $result = array_map(fn($item) => $item['property'], $ordered);
        return array_merge($result, $unordered);
    }

    /**
     * Determine if a property is excluded from schema via ExcludeFromSchema attribute.
     *
     * @param ReflectionProperty $property
     * @return bool
     */
    private static function isPropertyExcluded(ReflectionProperty $property): bool {
        $attributes = $property->getAttributes(ExcludeFromSchema::class);
        return !empty($attributes);
    }

    /**
     * Extract and merge all PropertySchema attributes into a single Schema.
     *
     * Multiple PropertySchema attributes (including subclasses) are merged in order
     * using Schema::merge(). This allows stacking multiple schema customizations
     * and creating reusable schema helper attributes.
     *
     * @param ReflectionProperty $property
     * @return Schema|null Returns the merged schema, or null if no attributes found.
     */
    private static function getPropertySchemaAttribute(ReflectionProperty $property): ?Schema {
        $attributes = $property->getAttributes(PropertySchemaInterface::class, \ReflectionAttribute::IS_INSTANCEOF);
        if (empty($attributes)) {
            return null;
        }

        $mergedSchema = null;
        foreach ($attributes as $attribute) {
            /** @var PropertySchemaInterface $propertySchemaAttr */
            $propertySchemaAttr = $attribute->newInstance();
            $attributeSchema = $propertySchemaAttr->getSchema();

            if ($mergedSchema === null) {
                $mergedSchema = $attributeSchema;
            } else {
                $mergedSchema->merge($attributeSchema);
            }
        }

        return $mergedSchema;
    }

    /**
     * Convert the entity to an array.
     *
     * Recursively converts nested entities and arrays of entities to arrays.
     * BackedEnum values are converted to their backing values.
     *
     * If a variant is provided, only properties included in that variant will be serialized.
     * The variant is propagated to nested entities, unless a property has a #[NestedVariant]
     * attribute that specifies a different variant for that nested entity.
     *
     * @param \BackedEnum|null $variant Optional variant to filter properties. If provided, sets the serializationVariant.
     * @return array
     */
    public function toArray(?\BackedEnum $variant = null): array {
        // If variant is explicitly passed, use it; otherwise use the instance's serializationVariant
        $effectiveVariant = $variant ?? $this->serializationVariant;

        $result = [];

        // Get schema properties for the effective variant (or full schema if no variant)
        // This is cached, so no reflection overhead here
        $schemaProperties = static::getSchema($effectiveVariant)->getSchemaArray()['properties'] ?? [];

        // get_object_vars returns only initialized properties, avoiding reflection
        $initializedProperties = get_object_vars($this);

        foreach ($schemaProperties as $name => $propertySchema) {
            if (!array_key_exists($name, $initializedProperties)) {
                continue;
            }

            // Skip schema-only properties (private/protected with PropertySchema attribute)
            // These are in the schema for documentation but can't be encoded/decoded
            if (!empty($propertySchema['x-schema-only'])) {
                continue;
            }

            $value = $initializedProperties[$name];

            // Read nested variant from cached schema metadata (set during schema generation)
            // This avoids reflection on every toArray() call
            $nestedVariant = $propertySchema['x-nested-serialization-variant'] ?? $effectiveVariant;

            $result[$name] = $this->valueToArrayWithVariant($value, $nestedVariant);
        }

        return $result;
    }

    /**
     * Convert the entity to an array using alternative property names.
     *
     * This method does a toArray() and then reverses the PropertyAltNames and MapSubProperties
     * mappings. This allows you to decode an entity from alt names and serialize it back to
     * the original structure.
     *
     * For PropertyAltNames: Uses the primaryAltName instead of the main property name.
     * For MapSubProperties: Extracts values from nested properties back to their original locations.
     *
     * @param \BackedEnum|null $variant Optional variant to filter properties. If provided, sets the serializationVariant.
     * @return array
     */
    public function toAltArray(?\BackedEnum $variant = null): array {
        $effectiveVariant = $variant ?? $this->serializationVariant;

        // Start with the normal toArray output
        $result = $this->toArray($effectiveVariant);

        // Get reflection info for this class
        $reflection = new ReflectionClass(static::class);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

        // Collect alt name mappings and sub-property mappings for properties in this variant
        $altNameMappings = [];
        $subPropertyMappings = [];

        foreach ($properties as $property) {
            if ($property->isStatic()) {
                continue;
            }
            if (self::isPropertyExcluded($property)) {
                continue;
            }
            if (!self::isPropertyInVariant($property, $effectiveVariant ?? SchemaVariant::Full)) {
                continue;
            }

            if (!$property->isInitialized($this)) {
                continue;
            }

            $propertyName = $property->getName();

            // Collect PropertyAltNames
            $altNameAttrs = $property->getAttributes(PropertyAltNames::class);
            if (!empty($altNameAttrs)) {
                /** @var PropertyAltNames $attr */
                $attr = $altNameAttrs[0]->newInstance();
                $primaryAltName = $attr->getPrimaryAltName();
                if ($primaryAltName !== null) {
                    $altNameMappings[$propertyName] = [
                        'primaryAltName' => $primaryAltName,
                        'useDotNotation' => $attr->useDotNotation(),
                    ];
                }
            }

            // Collect MapSubProperties
            $mapSubAttrs = $property->getAttributes(MapSubProperties::class);
            if (!empty($mapSubAttrs)) {
                /** @var MapSubProperties $attr */
                $attr = $mapSubAttrs[0]->newInstance();
                $keys = $attr->getKeys();
                $mapping = $attr->getMapping();
                if (!empty($keys) || !empty($mapping)) {
                    $subPropertyMappings[$propertyName] = [
                        'keys' => $keys,
                        'mapping' => $mapping,
                    ];
                }
            }
        }

        // Apply reverse MapSubProperties mappings first (extract from nested to root)
        foreach ($subPropertyMappings as $targetProperty => $config) {
            if (!array_key_exists($targetProperty, $result)) {
                continue;
            }

            $nestedData = $result[$targetProperty];
            if (!is_array($nestedData) && !$nestedData instanceof \ArrayObject) {
                continue;
            }

            // Convert ArrayObject to array for processing
            if ($nestedData instanceof \ArrayObject) {
                $nestedData = $nestedData->getArrayCopy();
            }

            $keys = $config['keys'];
            $mapping = $config['mapping'];

            // Reverse the keys: extract from nested property back to root
            foreach ($keys as $key) {
                $sentinel = new \stdClass();
                $value = ArrayUtils::getByPath($key, $nestedData, $sentinel);
                if ($value !== $sentinel) {
                    ArrayUtils::setByPath($key, $result, $value);
                }
            }

            // Reverse the mapping: extract from target paths back to source paths
            foreach ($mapping as $sourcePath => $targetPath) {
                $sentinel = new \stdClass();
                $value = ArrayUtils::getByPath($targetPath, $nestedData, $sentinel);
                if ($value !== $sentinel) {
                    ArrayUtils::setByPath($sourcePath, $result, $value);
                }
            }

            // Remove the nested property from result (it was constructed, not original)
            unset($result[$targetProperty]);
        }

        // Apply reverse PropertyAltNames mappings (rename properties to alt names)
        foreach ($altNameMappings as $propertyName => $config) {
            if (!array_key_exists($propertyName, $result)) {
                continue;
            }

            $primaryAltName = $config['primaryAltName'];
            $useDotNotation = $config['useDotNotation'];
            $value = $result[$propertyName];

            // Remove the main property name
            unset($result[$propertyName]);

            // Set the value at the alt name location
            if ($useDotNotation && str_contains($primaryAltName, '.')) {
                ArrayUtils::setByPath($primaryAltName, $result, $value);
            } else {
                $result[$primaryAltName] = $value;
            }
        }

        return $result;
    }

    /**
     * Convert a value to its array representation, propagating the variant to nested entities.
     *
     * @param mixed $value The value to convert.
     * @param \BackedEnum|null $variant The variant to propagate to nested entities.
     * @return mixed
     */
    private function valueToArrayWithVariant(mixed $value, ?\BackedEnum $variant): mixed {
        if ($value instanceof EntityInterface) {
            return $value->toArray($variant);
        }
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }
        if ($value instanceof UuidInterface) {
            return $value->toString();
        }
        if ($value instanceof \DateTimeInterface) {
            // Use RFC3339_EXTENDED if there are milliseconds, otherwise RFC3339
            $microseconds = (int) $value->format('u');
            if ($microseconds > 0) {
                return $value->format(\DateTimeInterface::RFC3339_EXTENDED);
            }
            return $value->format(\DateTimeInterface::RFC3339);
        }
        if ($value instanceof \ArrayObject) {
            // Preserve ArrayObject so empty objects serialize to {} not []
            // Recursively process values within the ArrayObject
            $result = new \ArrayObject();
            foreach ($value as $k => $v) {
                $result[$k] = $this->valueToArrayWithVariant($v, $variant);
            }
            return $result;
        }
        if (is_array($value)) {
            return array_map(fn($v) => $this->valueToArrayWithVariant($v, $variant), $value);
        }

        if ($value instanceof \JsonSerializable) {
            return $value->jsonSerialize();
        }
        return $value;
    }

    /**
     * Specify data which should be serialized to JSON.
     *
     * Uses the entity's serializationVariant if set.
     *
     * @return array
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize() {
        return $this->toArray($this->serializationVariant);
    }

    /**
     * Whether an offset exists.
     *
     * Checks if the property exists and is initialized, regardless of schema variants.
     *
     * @param mixed $offset
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function offsetExists($offset) {
        if (!property_exists(static::class, $offset)) {
            return false;
        }

        $reflection = new ReflectionProperty(static::class, $offset);
        if (!$reflection->isPublic() || $reflection->isStatic()) {
            return false;
        }

        return $reflection->isInitialized($this);
    }

    /**
     * Offset to retrieve.
     *
     * Returns the property value if it exists and is initialized, regardless of schema variants.
     *
     * @param mixed $offset
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function &offsetGet($offset) {
        if (!$this->offsetExists($offset)) {
            $null = null;
            return $null;
        }
        return $this->{$offset};
    }

    /**
     * Offset to set.
     *
     * Sets the property value if it's a public instance property, regardless of schema variants.
     *
     * @param mixed $offset
     * @param mixed $value
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value) {
        if (!property_exists(static::class, $offset)) {
            throw new \InvalidArgumentException("Property '$offset' does not exist on " . static::class . ".");
        }

        $reflection = new ReflectionProperty(static::class, $offset);
        if (!$reflection->isPublic() || $reflection->isStatic()) {
            throw new \InvalidArgumentException("Property '$offset' is not a public instance property.");
        }

        $this->{$offset} = $value;
    }

    /**
     * Offset to unset.
     *
     * @param mixed $offset
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset($offset) {
        throw new \BadMethodCallException("Cannot unset properties on an Entity.");
    }
}
