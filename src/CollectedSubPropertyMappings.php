<?php
/**
 * @author Adam Charron <adam@vanillaforums.com>
 * @copyright 2009-2024 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema;

use ReflectionProperty;

/**
 * Collected MapSubProperties mappings from an entity's properties.
 *
 * This class holds the MapSubProperties attribute instances keyed by their
 * target property name, and provides a `mapProperties()` method that
 * constructs nested property structures from flat or scattered input data.
 *
 * Usage:
 * ```php
 * $mapping = CollectedSubPropertyMappings::collect($reflectionProperties);
 * if (!$mapping->isEmpty()) {
 *     $data = $mapping->mapProperties($data);
 * }
 * ```
 */
class CollectedSubPropertyMappings {
    /**
     * @param array<string, MapSubProperties> $mappings Map of target property name to its MapSubProperties attribute.
     */
    private function __construct(
        private array $mappings,
    ) {}

    /**
     * Collect MapSubProperties attributes from a set of reflection properties.
     *
     * @param ReflectionProperty[] $properties Already-filtered properties to scan.
     * @return self
     */
    public static function collect(array $properties): self {
        $mappings = [];
        foreach ($properties as $property) {
            $attributes = $property->getAttributes(MapSubProperties::class);
            if (!empty($attributes)) {
                /** @var MapSubProperties $attr */
                $attr = $attributes[0]->newInstance();
                if (!empty($attr->getKeys()) || !empty($attr->getMapping())) {
                    $mappings[$property->getName()] = $attr;
                }
            }
        }
        return new self($mappings);
    }

    /**
     * Whether there are any sub-property mappings.
     *
     * @return bool
     */
    public function isEmpty(): bool {
        return empty($this->mappings);
    }

    /**
     * Map values from root data into nested property structures.
     *
     * For each mapped target property, extracts values from keys and mapped paths
     * in the source data and populates the target property array. The target property
     * is only created if at least one source value is found or the property already exists.
     *
     * @param array $data The input data to transform.
     * @return array The data with sub-properties populated.
     */
    public function mapProperties(array $data): array {
        foreach ($this->mappings as $targetProperty => $attr) {
            $existed = array_key_exists($targetProperty, $data);

            // Ensure target is an array we can modify
            if ($existed && !is_array($data[$targetProperty])) {
                continue;
            }

            if (!$existed) {
                $data[$targetProperty] = [];
            }

            $found = $attr->applyMappings($data, $data[$targetProperty]);

            // Remove the empty target if we created it and found nothing to populate
            if (!$found && !$existed) {
                unset($data[$targetProperty]);
            }
        }
        return $data;
    }
}
