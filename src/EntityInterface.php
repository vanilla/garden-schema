<?php
/**
 * @author Adam Charron <adam@vanillaforums.com>
 * @copyright 2009-2024 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema;

/**
 * Interface for strongly-typed data objects that automatically generate schemas from properties.
 *
 * This interface defines the contract for Entity-like classes that:
 * - Generate schemas from their property definitions
 * - Support validation and hydration from raw data
 * - Support schema variants for different API use cases
 * - Can serialize to arrays
 *
 * Use this interface when you have a class that already extends another class
 * and cannot extend the abstract Entity class directly. Implement this interface
 * and use reflection-based logic similar to Entity, or provide your own
 * schema generation logic.
 *
 * For most use cases, extending the abstract Entity class is recommended as it
 * provides a complete implementation with caching, ArrayAccess, and JsonSerializable.
 *
 * @see Entity For the reference implementation
 */
interface EntityInterface {
    /**
     * Build and return the schema for this entity.
     *
     * Implementations should cache the schema for performance.
     *
     * @param \BackedEnum|null $variant The schema variant to generate. Defaults to SchemaVariant::Full.
     * @return Schema
     */
    public static function getSchema(?\BackedEnum $variant = null): Schema;

    /**
     * Validate and cast the value into an entity instance.
     *
     * If the value is already an instance of the target entity class, it should be returned as-is.
     *
     * @param mixed $value The data to validate and convert to an entity.
     * @param \BackedEnum|null $variant The schema variant to validate against. Defaults to Full.
     * @return static
     * @throws ValidationException If validation fails.
     */
    public static function from(mixed $value, ?\BackedEnum $variant = null): static;

    /**
     * Hydrate an entity instance from already-validated data.
     *
     * This method should skip validation and directly assign properties.
     * Use this when the data has already been validated by the schema.
     *
     * @param array $clean The validated data array.
     * @param \BackedEnum|null $variant The schema variant used for validation.
     * @return static
     */
    public static function fromValidated(array $clean, ?\BackedEnum $variant = null): static;

    /**
     * Convert the entity to an array.
     *
     * Should recursively convert nested entities and arrays of entities to arrays.
     * BackedEnum values should be converted to their backing values.
     *
     * @param \BackedEnum|null $variant Optional variant to filter properties.
     * @return array
     */
    public function toArray(?\BackedEnum $variant = null): array;

    /**
     * Validate the entity's current state against its schema.
     *
     * This is useful after direct property modifications to verify
     * that the entity is still in a valid state.
     *
     * @param \BackedEnum|null $variant The schema variant to validate against.
     * @return static A new validated entity instance.
     * @throws ValidationException If the entity's current state is invalid.
     */
    public function validate(?\BackedEnum $variant = null): static;

    /**
     * Set the serialization variant for this entity.
     *
     * When set, toArray() and JSON serialization will only include properties
     * that are part of the specified variant.
     *
     * @param \BackedEnum|null $variant The variant to use for serialization.
     * @return static
     */
    public function setSerializationVariant(?\BackedEnum $variant): static;

    /**
     * Get the current serialization variant.
     *
     * @return \BackedEnum|null
     */
    public function getSerializationVariant(): ?\BackedEnum;
}
