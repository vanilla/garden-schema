<?php
/**
 * @author Adam Charron <adam@vanillaforums.com>
 * @copyright 2009-2024 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema;

/**
 * Trait providing default implementations for part of EntityInterface.
 *
 * This trait provides implementations for:
 * - from() - Validate and create an instance
 * - validate() - Validate the entity's current state
 * - setSerializationVariant() / getSerializationVariant() - Manage serialization variant
 *
 * Classes using this trait must still implement:
 * - getSchema() - Define how to build/retrieve the schema
 * - fromValidated() - Define how to hydrate the entity from validated data
 * - toArray() - Define how to serialize the entity to an array
 *
 * Usage:
 * ```php
 * class MyEntity extends SomeBaseClass implements EntityInterface {
 *     use EntityTrait;
 *
 *     public int $id;
 *     public string $name;
 *
 *     public static function getSchema(?\BackedEnum $variant = null): Schema {
 *         // Your schema generation logic
 *     }
 *
 *     public static function fromValidated(array $clean, ?\BackedEnum $variant = null): static {
 *         // Your hydration logic
 *     }
 *
 *     public function toArray(?\BackedEnum $variant = null): array {
 *         // Your serialization logic
 *     }
 * }
 * ```
 */
trait EntityTrait {
    /**
     * The variant used for serialization (toArray/JSON).
     * When set, only properties included in this variant will be serialized.
     *
     * @var \BackedEnum|null
     */
    protected ?\BackedEnum $serializationVariant = null;

    /**
     * Validate and cast the value into an entity instance.
     *
     * If the value is already an instance of the target entity class, it is returned as-is.
     *
     * @param mixed $value The data to validate and convert to an entity.
     * @param \BackedEnum|null $variant The schema variant to validate against. Defaults to Full.
     * @param bool $sparseValidation Whether to perform sparse validation. Defaults to false.
     *
     * @return static
     * @throws ValidationException If validation fails.
     */
    public static function from(mixed $value, ?\BackedEnum $variant = null, bool $sparseValidation = false): static {
        if ($value instanceof static) {
            return $value;
        }
        $schema = static::getSchema($variant);
        if ($sparseValidation) {
            $schema = $schema->withSparse();
        }
        $clean = $schema->validate($value);
        return static::fromValidated($clean, $variant);
    }

    /**
     * Validate the entity's current state against its schema.
     *
     * This is useful after direct property modifications (which bypass validation) to verify
     * that the entity is still in a valid state. Returns a new validated entity instance
     * with the same data, or throws a ValidationException if invalid.
     *
     * @param \BackedEnum|null $variant The schema variant to validate against. Defaults to Full.
     * @return static A new validated entity instance.
     * @throws ValidationException If the entity's current state is invalid.
     */
    public function validate(?\BackedEnum $variant = null): static {
        return static::from($this->toArray($variant), $variant);
    }

    /**
     * Set the serialization variant for this entity.
     *
     * When set, toArray() and JSON serialization will only include properties
     * that are part of the specified variant.
     *
     * @param \BackedEnum|null $variant The variant to use for serialization, or null to include all properties.
     * @return static
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
}
