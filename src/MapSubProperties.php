<?php
/**
 * @author Adam Charron <adam@vanillaforums.com>
 * @copyright 2009-2024 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema;

use Garden\Utils\ArrayUtils;

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

    /**
     * Apply the sub-property mappings from source data into the target array.
     *
     * Iterates through keys and mappings, extracting values from $sourceData
     * via dot-notation paths and writing them into $target.
     *
     * @param array $sourceData The root data to extract values from.
     * @param array &$target The target array to populate.
     * @return bool Whether any values were found and applied.
     */
    public function applyMappings(array $sourceData, array &$target): bool {
        $sentinel = new \stdClass();
        $found = false;

        foreach ($this->keys as $key) {
            $value = ArrayUtils::getByPath($key, $sourceData, $sentinel);
            if ($value !== $sentinel) {
                ArrayUtils::setByPath($key, $target, $value);
                $found = true;
            }
        }

        foreach ($this->mapping as $sourcePath => $targetPath) {
            $value = ArrayUtils::getByPath($sourcePath, $sourceData, $sentinel);
            if ($value !== $sentinel) {
                ArrayUtils::setByPath($targetPath, $target, $value);
                $found = true;
            }
        }

        return $found;
    }

    /**
     * Reverse the sub-property mappings: extract values from nested data back to the root result.
     *
     * This is the inverse of applyMappings(). Keys are extracted from their nested
     * location back to the root. Mapped paths are extracted from their target paths
     * back to their original source paths.
     *
     * @param array $nestedData The nested property data to extract values from.
     * @param array &$result The root result array to write values into.
     */
    public function reverseMappings(array $nestedData, array &$result): void {
        $sentinel = new \stdClass();

        foreach ($this->keys as $key) {
            $value = ArrayUtils::getByPath($key, $nestedData, $sentinel);
            if ($value !== $sentinel) {
                ArrayUtils::setByPath($key, $result, $value);
            }
        }

        foreach ($this->mapping as $sourcePath => $targetPath) {
            $value = ArrayUtils::getByPath($targetPath, $nestedData, $sentinel);
            if ($value !== $sentinel) {
                ArrayUtils::setByPath($sourcePath, $result, $value);
            }
        }
    }

    /**
     * Derive bidirectional field name entries for this attribute's keys and mappings.
     *
     * Keys produce: flat key (alt) <-> targetProperty.key (canonical)
     * Mappings produce: sourcePath (alt) <-> targetProperty.targetPath (canonical)
     *
     * @param string $targetPropertyName The canonical name of the property this attribute is on.
     * @return array{canonicalToAlt: array<string, string>, altToCanonical: array<string, string>}
     */
    public function deriveFieldNameEntries(string $targetPropertyName): array {
        $canonicalToAlt = [];
        $altToCanonical = [];

        foreach ($this->keys as $key) {
            $canonicalPath = $targetPropertyName . '.' . $key;
            $canonicalToAlt[$canonicalPath] = $key;
            $altToCanonical[$key] = $canonicalPath;
        }

        foreach ($this->mapping as $sourcePath => $targetPath) {
            $canonicalPath = $targetPropertyName . '.' . $targetPath;
            $canonicalToAlt[$canonicalPath] = $sourcePath;
            $altToCanonical[$sourcePath] = $canonicalPath;
        }

        return [
            'canonicalToAlt' => $canonicalToAlt,
            'altToCanonical' => $altToCanonical,
        ];
    }
}
