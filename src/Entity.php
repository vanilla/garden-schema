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
 */
abstract class Entity implements \ArrayAccess, \JsonSerializable {
    /**
     * @var array<class-string, Schema>
     */
    private static array $schemaCache = [];

    /**
     * Track classes currently being built to prevent infinite recursion with self-referencing entities.
     *
     * @var array<class-string, bool>
     */
    private static array $schemaBuilding = [];

    /**
     * Build and return the schema for this entity using reflection.
     *
     * @return Schema
     */
    public static function getSchema(): Schema {
        $class = static::class;
        if (isset(self::$schemaCache[$class])) {
            return self::$schemaCache[$class];
        }

        // Detect self-referencing recursion
        if (isset(self::$schemaBuilding[$class])) {
            throw new \RuntimeException("Circular reference detected while building schema for {$class}. Use getSchema() only after schema is fully built.");
        }

        self::$schemaBuilding[$class] = true;

        try {
            $reflection = new ReflectionClass($class);
            $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);
            $schemaProperties = [];
            $required = [];

            foreach ($properties as $property) {
                if ($property->isStatic()) {
                    continue;
                }

                if (self::isPropertyExcluded($property)) {
                    continue;
                }

                $propertyName = $property->getName();
                $propertySchema = self::buildPropertySchema($property, $class);
                $schemaProperties[$propertyName] = $propertySchema;

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
            $altNameMappings = self::collectAltNameMappings($properties);
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

            self::$schemaCache[$class] = $schema;
            return $schema;
        } finally {
            unset(self::$schemaBuilding[$class]);
        }
    }

    /**
     * Clear the cached schema for a specific class or all classes.
     *
     * Useful for testing or scenarios where entity definitions may change at runtime.
     *
     * @param class-string|null $class The class to clear cache for, or null to clear all.
     */
    public static function invalidateSchemaCache(?string $class = null): void {
        if ($class === null) {
            self::$schemaCache = [];
        } else {
            unset(self::$schemaCache[$class]);
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
     * Convert the entity to an array.
     *
     * Recursively converts nested entities and arrays of entities to arrays.
     * BackedEnum values are converted to their backing values.
     *
     * @return array
     */
    public function toArray(): array {
        $result = [];
        $reflection = new ReflectionClass(static::class);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);
        $schemaProperties = static::getSchema()->getSchemaArray()['properties'] ?? [];

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
            $result[$name] = self::valueToArray($value);
        }

        return $result;
    }

    /**
     * Convert a value to its array representation.
     *
     * @param mixed $value
     * @return mixed
     */
    private static function valueToArray($value) {
        if ($value instanceof self) {
            return $value->toArray();
        }
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }
        if ($value instanceof \ArrayObject) {
            // Preserve ArrayObject so empty objects serialize to {} not []
            // Recursively process values within the ArrayObject
            $result = new \ArrayObject();
            foreach ($value as $k => $v) {
                $result[$k] = self::valueToArray($v);
            }
            return $result;
        }
        if (is_array($value)) {
            return array_map([self::class, 'valueToArray'], $value);
        }
        return $value;
    }

    /**
     * Specify data which should be serialized to JSON.
     *
     * @return array
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize() {
        return $this->toArray();
    }

    /**
     * Whether an offset exists.
     *
     * @param mixed $offset
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function offsetExists($offset) {
        $schemaProperties = static::getSchema()->getSchemaArray()['properties'] ?? [];
        if (!array_key_exists($offset, $schemaProperties)) {
            return false;
        }

        if (!property_exists(static::class, $offset)) {
            return false;
        }

        $reflection = new ReflectionProperty(static::class, $offset);
        return $reflection->isInitialized($this);
    }

    /**
     * Offset to retrieve.
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
     * @param mixed $offset
     * @param mixed $value
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value) {
        $schemaProperties = static::getSchema()->getSchemaArray()['properties'] ?? [];
        if (!array_key_exists($offset, $schemaProperties)) {
            throw new \InvalidArgumentException("Property '$offset' is not defined in the schema.");
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
