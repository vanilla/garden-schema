<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema;

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
abstract class Entity implements \ArrayAccess, \JsonSerializable {
    /**
     * Cache for generated schemas, keyed by "class::variant".
     *
     * @var array<string, Schema>
     */
    private static array $schemaCache = [];

    /**
     * Track classes currently being built to prevent infinite recursion with self-referencing entities.
     *
     * @var array<string, bool>
     */
    private static array $schemaBuilding = [];

    /**
     * The variant used for serialization (toArray/JSON).
     * When set, only properties included in this variant will be serialized.
     * This is propagated to nested entities during serialization.
     *
     * @var \BackedEnum|null
     */
    protected ?\BackedEnum $serializationVariant = null;

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
        $variantClass = $variant::class;
        $cacheKey = "{$class}::{$variantClass}::{$variant->value}";

        if (isset(self::$schemaCache[$cacheKey])) {
            return self::$schemaCache[$cacheKey];
        }

        // Detect self-referencing recursion
        if (isset(self::$schemaBuilding[$cacheKey])) {
            throw new \RuntimeException("Circular reference detected while building schema for {$class}. Use getSchema() only after schema is fully built.");
        }

        self::$schemaBuilding[$cacheKey] = true;

        try {
            $reflection = new ReflectionClass($class);
            $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);
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

                // Check if property should be included in this variant
                if (!self::isPropertyInVariant($property, $variant)) {
                    continue;
                }

                $propertyName = $property->getName();
                $propertySchema = self::buildPropertySchema($property, $class);
                $schemaProperties[$propertyName] = $propertySchema;
                $includedProperties[] = $property;

                if (self::isPropertyRequired($property)) {
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
                    foreach ($altNameMappings as $mainName => $altNames) {
                        if (!array_key_exists($mainName, $data)) {
                            foreach ($altNames as $altName) {
                                if (array_key_exists($altName, $data)) {
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

            self::$schemaCache[$cacheKey] = $schema;
            return $schema;
        } finally {
            unset(self::$schemaBuilding[$cacheKey]);
        }
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
        $excludeAttrs = $property->getAttributes(ExcludeFromVariant::class);
        foreach ($excludeAttrs as $excludeAttr) {
            /** @var ExcludeFromVariant $exclude */
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
            self::$schemaCache = [];
        } elseif ($variant === null) {
            // Clear all variants for this class by matching prefix
            $prefix = "{$class}::";
            foreach (array_keys(self::$schemaCache) as $key) {
                if (str_starts_with($key, $prefix)) {
                    unset(self::$schemaCache[$key]);
                }
            }
        } else {
            // Clear specific variant for this class
            $variantClass = $variant::class;
            $cacheKey = "{$class}::{$variantClass}::{$variant->value}";
            unset(self::$schemaCache[$cacheKey]);
        }
    }

    /**
     * Collect alternative name mappings from PropertyAltNames attributes.
     *
     * @param ReflectionProperty[] $properties
     * @return array<string, string[]> Map of property name to alternative names.
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
                    $mappings[$property->getName()] = $altNames;
                }
            }
        }
        return $mappings;
    }

    /**
     * Validate and cast the value into an entity instance.
     *
     * If the value is already an instance of the target entity class, it is returned as-is.
     *
     * @param mixed $value
     * @return static
     */
    public static function from($value) {
        if ($value instanceof static) {
            return $value;
        }
        $clean = static::getSchema()->validate($value);
        return static::fromValidated($clean);
    }

    /**
     * Hydrate an entity instance from already-validated data.
     *
     * This method skips validation and directly assigns properties.
     * Use this when the data has already been validated by the schema.
     *
     * @param array $clean The validated data array.
     * @return static
     */
    public static function fromValidated(array $clean) {
        $entity = new static();
        $reflection = new ReflectionClass(static::class);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);
        $schemaProperties = static::getSchema()->getSchemaArray()['properties'] ?? [];

        foreach ($properties as $property) {
            if ($property->isStatic()) {
                continue;
            }

            if (self::isPropertyExcluded($property)) {
                continue;
            }

            $name = $property->getName();

            // Initialize nullable properties with null if not provided
            if (!array_key_exists($name, $clean)) {
                $type = self::getPropertyType($property);
                if ($type !== null && $type->allowsNull() && !$property->hasDefaultValue()) {
                    $entity->{$name} = null;
                }
                continue;
            }

            $value = $clean[$name];
            $type = self::getPropertyType($property);

            if ($type !== null && !$type->isBuiltin()) {
                $typeName = $type->getName();
                // Handle single nested entity
                if (is_subclass_of($typeName, self::class)) {
                    if ($value === null) {
                        $entity->{$name} = null;
                        continue;
                    }
                    if (!$value instanceof $typeName) {
                        $value = $typeName::fromValidated($value);
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
                }
            } elseif (is_array($value)) {
                // Handle arrays of nested entities via PropertySchema attribute
                $propertySchema = $schemaProperties[$name] ?? [];
                $itemsEntityClass = $propertySchema['items']['entityClassName'] ?? null;
                if ($itemsEntityClass && is_subclass_of($itemsEntityClass, self::class)) {
                    $value = array_map(function ($item) use ($itemsEntityClass) {
                        if ($item instanceof $itemsEntityClass) {
                            return $item;
                        }
                        if (is_array($item)) {
                            return $itemsEntityClass::fromValidated($item);
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
     * Validate the entity's current state against its schema.
     *
     * This is useful after direct property modifications (which bypass validation) to verify
     * that the entity is still in a valid state. Returns a new validated entity instance
     * with the same data, or throws a ValidationException if invalid.
     *
     * @return static A new validated entity instance.
     * @throws ValidationException If the entity's current state is invalid.
     */
    public function validate(): static {
        return static::from($this->toArray());
    }

    /**
     * Build a schema array for a property.
     *
     * @param ReflectionProperty $property
     * @param class-string $currentClass The class currently being built (to detect self-reference).
     * @return array
     */
    private static function buildPropertySchema(ReflectionProperty $property, string $currentClass): array {
        $schema = [];
        $type = self::getPropertyType($property);

        if ($type !== null) {
            $typeName = $type->getName();
            if ($type->isBuiltin()) {
                $schemaType = self::mapBuiltinType($typeName);
                if ($schemaType !== null) {
                    $schema['type'] = $schemaType;
                }
            } else {
                if (is_subclass_of($typeName, self::class)) {
                    // For self-referencing entities, don't recursively get the full schema
                    // Just mark with entityClassName and let Schema handle it lazily
                    if ($typeName === $currentClass) {
                        $schema['type'] = 'object';
                        $schema['entityClassName'] = $typeName;
                    } else {
                        $schema = $typeName::getSchema()->getSchemaArray();
                        $schema['entityClassName'] = $typeName;
                    }
                } elseif (is_subclass_of($typeName, \BackedEnum::class)) {
                    $schema = self::buildEnumSchema($typeName);
                } elseif ($typeName === \ArrayObject::class || is_subclass_of($typeName, \ArrayObject::class)) {
                    $schema['type'] = 'object';
                } elseif ($typeName === \DateTimeImmutable::class || is_subclass_of($typeName, \DateTimeImmutable::class)) {
                    $schema['type'] = 'string';
                    $schema['format'] = 'date-time';
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
        if (!empty($attributeSchema)) {
            $schema = array_replace_recursive($schema, $attributeSchema);
        }

        return $schema;
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
     * @param ReflectionProperty $property
     * @return bool
     */
    private static function isPropertyRequired(ReflectionProperty $property): bool {
        $type = self::getPropertyType($property);
        if ($property->hasDefaultValue()) {
            return false;
        }
        if ($type !== null && $type->allowsNull()) {
            return false;
        }
        return true;
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
     * Extract the PropertySchema attribute as a schema array.
     *
     * @param ReflectionProperty $property
     * @return array
     */
    private static function getPropertySchemaAttribute(ReflectionProperty $property): array {
        $attributes = $property->getAttributes(PropertySchema::class);
        if (empty($attributes)) {
            return [];
        }

        /** @var PropertySchema $attribute */
        $attribute = $attributes[0]->newInstance();
        return $attribute->getSchema();
    }

    /**
     * Set the serialization variant for this entity.
     *
     * When set, toArray() and JSON serialization will only include properties
     * that are part of the specified variant.
     *
     * @param \BackedEnum|null $variant The variant to use for serialization, or null to include all properties.
     * @return $this
     */
    public function setSerializationVariant(?\BackedEnum $variant): static {
        $this->serializationVariant = $variant;
        return $this;
    }

    /**
     * Get the current serialization variant.
     *
     * @return \BackedEnum|null
     */
    public function getSerializationVariant(): ?\BackedEnum {
        return $this->serializationVariant;
    }

    /**
     * Convert the entity to an array.
     *
     * Recursively converts nested entities and arrays of entities to arrays.
     * BackedEnum values are converted to their backing values.
     *
     * If a variant is provided, only properties included in that variant will be serialized.
     * The variant is propagated to nested entities.
     *
     * @param \BackedEnum|null $variant Optional variant to filter properties. If provided, sets the serializationVariant.
     * @return array
     */
    public function toArray(?\BackedEnum $variant = null): array {
        // If variant is explicitly passed, use it; otherwise use the instance's serializationVariant
        $effectiveVariant = $variant ?? $this->serializationVariant;

        $result = [];
        $reflection = new ReflectionClass(static::class);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

        // Get schema properties for the effective variant (or full schema if no variant)
        $schemaProperties = static::getSchema($effectiveVariant)->getSchemaArray()['properties'] ?? [];

        foreach ($properties as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $name = $property->getName();
            if (!array_key_exists($name, $schemaProperties)) {
                continue;
            }

            if (!$property->isInitialized($this)) {
                continue;
            }

            $value = $this->{$name};
            $result[$name] = $this->valueToArrayWithVariant($value, $effectiveVariant);
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
        if ($value instanceof Entity) {
            return $value->toArray($variant);
        }
        if ($value instanceof \BackedEnum) {
            return $value->value;
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
