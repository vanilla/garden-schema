<?php
/**
 * @author Adam Charron <adam@charrondev.com>
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema;

/**
 * Interface for entities that provide a default instance.
 *
 * When a nested entity property's type implements this interface:
 * - The schema will include the default value (for documentation/OpenAPI)
 * - The default instance will be used during hydration when the property is not provided
 *
 * This solves the PHP limitation where class properties cannot have default values
 * that require instantiation (like nested entities).
 *
 * Example:
 * ```php
 * class MetadataEntity extends Entity implements EntityDefaultInterface {
 *     public string $version = '1.0';
 *     public bool $draft = true;
 *
 *     public static function default(): static {
 *         $instance = new static();
 *         $instance->version = '1.0';
 *         $instance->draft = true;
 *         return $instance;
 *     }
 * }
 *
 * class ArticleEntity extends Entity {
 *     public string $title;
 *     public MetadataEntity $metadata;  // Will get default automatically
 * }
 * ```
 */
interface EntityDefaultInterface {
    /**
     * Return a default instance of this entity.
     *
     * Called during schema generation to populate the 'default' key,
     * and during hydration when the property is not provided in the input data.
     *
     * @return static
     */
    public static function default(): static;
}
