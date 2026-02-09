<?php
/**
 * @author Adam Charron <adam@vanillaforums.com>
 * @copyright 2009-2024 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema;

use ReflectionProperty;

/**
 * Collected PropertyAltNames mappings from an entity's properties.
 *
 * This class holds the PropertyAltNames attribute instances keyed by their
 * canonical property name, and provides a `mapProperties()` method that
 * resolves alternative names in input data to their canonical equivalents.
 *
 * Usage:
 * ```php
 * $mapping = CollectedAltNameMapping::collect($reflectionProperties);
 * if (!$mapping->isEmpty()) {
 *     $data = $mapping->mapProperties($data);
 * }
 * ```
 */
class CollectedAltNameMapping {
    /**
     * @param array<string, PropertyAltNames> $mappings Map of canonical property name to its PropertyAltNames attribute.
     */
    private function __construct(
        private array $mappings,
    ) {}

    /**
     * Collect PropertyAltNames attributes from a set of reflection properties.
     *
     * @param ReflectionProperty[] $properties Already-filtered properties to scan.
     * @return self
     */
    public static function collect(array $properties): self {
        $mappings = [];
        foreach ($properties as $property) {
            $attributes = $property->getAttributes(PropertyAltNames::class);
            if (!empty($attributes)) {
                /** @var PropertyAltNames $attr */
                $attr = $attributes[0]->newInstance();
                if (!empty($attr->getAltNames())) {
                    $mappings[$property->getName()] = $attr;
                }
            }
        }
        return new self($mappings);
    }

    /**
     * Whether there are any alt name mappings.
     *
     * @return bool
     */
    public function isEmpty(): bool {
        return empty($this->mappings);
    }

    /**
     * Map alternative property names to their canonical equivalents in the data.
     *
     * For each mapped property, if the canonical name is not present in the data,
     * the PropertyAltNames attribute is used to resolve a value from its alt names.
     * Non-dot-notation matches are removed from the data (renamed to canonical).
     * Dot-notation matches are copied (source left intact).
     *
     * @param array $data The input data to transform.
     * @return array The data with alt names resolved to canonical names.
     */
    public function mapProperties(array $data): array {
        foreach ($this->mappings as $mainName => $attr) {
            if (array_key_exists($mainName, $data)) {
                continue;
            }
            $sentinel = new \stdClass();
            $value = $attr->resolveFromData($data, $sentinel);
            if ($value !== $sentinel) {
                $data[$mainName] = $value;
            }
        }
        return $data;
    }
}
