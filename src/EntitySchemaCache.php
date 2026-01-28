<?php
/**
 * @author Adam Charron <adam@vanillaforums.com>
 * @copyright 2009-2024 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema;

/**
 * Utility class for caching entity schemas.
 *
 * This class provides a centralized cache for entity schemas, allowing for efficient
 * schema retrieval across multiple entity types and variants. The cache is keyed by
 * a combination of class name, variant enum class, and variant value.
 *
 * Usage:
 * ```php
 * // Get or create a schema for an entity
 * $schema = EntitySchemaCache::getOrCreate(
 *     MyEntity::class,
 *     SchemaVariant::Full,
 *     function() {
 *         // Build and return the schema
 *         return new Schema([...]);
 *     }
 * );
 *
 * // Invalidate cache for a specific class/variant
 * EntitySchemaCache::invalidate(MyEntity::class, SchemaVariant::Full);
 *
 * // Invalidate all variants for a class
 * EntitySchemaCache::invalidate(MyEntity::class);
 *
 * // Invalidate all cached schemas
 * EntitySchemaCache::invalidateAll();
 * ```
 */
final class EntitySchemaCache {
    /**
     * Cache for generated schemas, keyed by "class::variantClass::variantValue".
     *
     * @var array<string, Schema>
     */
    private static array $cache = [];

    /**
     * Track classes currently being built to prevent infinite recursion with self-referencing entities.
     *
     * @var array<string, bool>
     */
    private static array $building = [];

    /**
     * Prevent instantiation.
     */
    private function __construct() {}

    /**
     * Get a cached schema or create one using the provided factory.
     *
     * If a schema for the given class and variant already exists in the cache,
     * it is returned. Otherwise, the factory callable is invoked to create the schema,
     * which is then cached and returned.
     *
     * @param class-string $class The entity class name.
     * @param \BackedEnum|null $variant The schema variant. Defaults to SchemaVariant::Full.
     * @param callable(): Schema $factory A callable that returns a Schema when invoked.
     * @return Schema
     * @throws \RuntimeException If circular reference is detected during schema building.
     */
    public static function getOrCreate(string $class, ?\BackedEnum $variant, callable $factory): Schema {
        $variant ??= SchemaVariant::Full;
        $cacheKey = self::buildCacheKey($class, $variant);

        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        // Detect self-referencing recursion
        if (isset(self::$building[$cacheKey])) {
            throw new \RuntimeException(
                "Circular reference detected while building schema for {$class}. " .
                "Use getSchema() only after schema is fully built."
            );
        }

        self::$building[$cacheKey] = true;

        try {
            $schema = $factory();
            self::$cache[$cacheKey] = $schema;
            return $schema;
        } finally {
            unset(self::$building[$cacheKey]);
        }
    }

    /**
     * Check if a schema is cached for the given class and variant.
     *
     * @param class-string $class The entity class name.
     * @param \BackedEnum|null $variant The schema variant. Defaults to SchemaVariant::Full.
     * @return bool
     */
    public static function has(string $class, ?\BackedEnum $variant = null): bool {
        $variant ??= SchemaVariant::Full;
        $cacheKey = self::buildCacheKey($class, $variant);
        return isset(self::$cache[$cacheKey]);
    }

    /**
     * Get a cached schema without creating one.
     *
     * @param class-string $class The entity class name.
     * @param \BackedEnum|null $variant The schema variant. Defaults to SchemaVariant::Full.
     * @return Schema|null The cached schema, or null if not cached.
     */
    public static function get(string $class, ?\BackedEnum $variant = null): ?Schema {
        $variant ??= SchemaVariant::Full;
        $cacheKey = self::buildCacheKey($class, $variant);
        return self::$cache[$cacheKey] ?? null;
    }

    /**
     * Invalidate cached schema for a specific class and optionally a specific variant.
     *
     * @param class-string $class The class to clear cache for.
     * @param \BackedEnum|null $variant The specific variant to clear, or null to clear all variants for the class.
     */
    public static function invalidate(string $class, ?\BackedEnum $variant = null): void {
        if ($variant !== null) {
            // Clear specific variant for this class
            $cacheKey = self::buildCacheKey($class, $variant);
            unset(self::$cache[$cacheKey]);
        } else {
            // Clear all variants for this class by matching prefix
            $prefix = "{$class}::";
            foreach (array_keys(self::$cache) as $key) {
                if (str_starts_with($key, $prefix)) {
                    unset(self::$cache[$key]);
                }
            }
        }
    }

    /**
     * Invalidate all cached schemas.
     *
     * Useful for testing or scenarios where all entity definitions may change at runtime.
     */
    public static function invalidateAll(): void {
        self::$cache = [];
    }

    /**
     * Get all cached schemas.
     *
     * Primarily useful for debugging and testing.
     *
     * @return array<string, Schema>
     */
    public static function getAll(): array {
        return self::$cache;
    }

    /**
     * Get the count of cached schemas.
     *
     * @return int
     */
    public static function count(): int {
        return count(self::$cache);
    }

    /**
     * Build a cache key for a class and variant combination.
     *
     * @param class-string $class The entity class name.
     * @param \BackedEnum $variant The schema variant.
     * @return string
     */
    private static function buildCacheKey(string $class, \BackedEnum $variant): string {
        $variantClass = $variant::class;
        return "{$class}::{$variantClass}::{$variant->value}";
    }
}
