<?php
/**
 * @author Adam Charron <adam@vanillaforums.com>
 * @copyright 2009-2024 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema;

/**
 * Attribute to control the serialization variant of a nested entity property.
 *
 * By default, when an entity is serialized with toArray($variant), all nested entities
 * inherit the same variant. Use this attribute to specify a different variant for
 * a specific nested entity property.
 *
 * This is particularly useful when:
 * - A parent entity serializes as Full, but a nested entity should be a Fragment
 * - Embedding related entities in API responses where you want reduced data
 * - Preventing deeply nested full expansions
 *
 * Example - always serialize author as Fragment:
 *
 * ```php
 * class ArticleEntity extends Entity {
 *     public int $id;
 *     public string $title;
 *     public string $body;
 *
 *     #[NestedVariant(SchemaVariant::Fragment)]
 *     public AuthorEntity $author;
 * }
 *
 * // When serializing:
 * $article->toArray(SchemaVariant::Full);
 * // The article serializes as Full, but $author serializes as Fragment
 * ```
 *
 * Note: This attribute only applies to properties typed as EntityInterface implementations
 * or arrays of entities. It has no effect on scalar properties.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class NestedVariant {
    /**
     * @var \BackedEnum The variant to use for the nested entity.
     */
    private \BackedEnum $variant;

    /**
     * @param \BackedEnum $variant The variant to use for serializing the nested entity.
     */
    public function __construct(\BackedEnum $variant) {
        $this->variant = $variant;
    }

    /**
     * Get the variant for the nested entity.
     *
     * @return \BackedEnum
     */
    public function getVariant(): \BackedEnum {
        return $this->variant;
    }

    /**
     * Resolve which variant to use for the nested entity.
     *
     * @param \BackedEnum|null $parentVariant The variant the parent entity is being serialized with (unused, kept for API consistency).
     * @return \BackedEnum The variant to use for the nested entity.
     */
    public function resolveVariant(?\BackedEnum $parentVariant): \BackedEnum {
        return $this->variant;
    }
}
