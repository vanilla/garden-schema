<?php
/**
 * @author Adam Charron <adam@vanillaforums.com>
 * @copyright 2009-2024 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema;

/**
 * Attribute to map properties from the root data into a nested property structure.
 *
 * This is useful when you need to construct a nested Entity or ArrayObject from
 * flat data or data scattered across different paths in the input.
 *
 * Example usage:
 * ```php
 * class ArticleEntity extends Entity {
 *     #[MapSubProperties(
 *         keys: ['authorID', 'authorName'],
 *         mapping: ['metadata.authorEmail' => 'email', 'metadata.authorBio' => 'bio']
 *     )]
 *     public AuthorEntity $author;
 * }
 *
 * // Input data:
 * ArticleEntity::from([
 *     'title' => 'My Article',
 *     'authorID' => 123,
 *     'authorName' => 'John Doe',
 *     'metadata' => [
 *         'authorEmail' => 'john@example.com',
 *         'authorBio' => 'A writer',
 *     ],
 * ]);
 *
 * // Before validation, data is transformed to:
 * [
 *     'title' => 'My Article',
 *     'authorID' => 123,
 *     'authorName' => 'John Doe',
 *     'metadata' => ['authorEmail' => 'john@example.com', 'authorBio' => 'A writer'],
 *     'author' => [
 *         'authorID' => 123,
 *         'authorName' => 'John Doe',
 *         'email' => 'john@example.com',
 *         'bio' => 'A writer',
 *     ],
 * ]
 * ```
 *
 * Note: Values are copied (not moved) from source to target. Missing source paths are silently skipped.
 * Both source and target paths support dot notation for nested access.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class MapSubProperties {
    /**
     * @var string[] Flat keys to copy from root data into the target property.
     */
    private array $keys;

    /**
     * @var array<string, string> Mapping of source paths to target paths.
     */
    private array $mapping;

    /**
     * @param string[] $keys Flat keys to copy from root data into the target property.
     * @param array<string, string> $mapping Mapping of source paths to target paths. Both support dot notation.
     */
    public function __construct(array $keys = [], array $mapping = []) {
        $this->keys = $keys;
        $this->mapping = $mapping;
    }

    /**
     * Get the flat keys to copy.
     *
     * @return string[]
     */
    public function getKeys(): array {
        return $this->keys;
    }

    /**
     * Get the source-to-target path mapping.
     *
     * @return array<string, string>
     */
    public function getMapping(): array {
        return $this->mapping;
    }
}
