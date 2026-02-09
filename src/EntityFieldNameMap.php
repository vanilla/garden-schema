<?php
/**
 * @author Adam Charron <adam@vanillaforums.com>
 * @copyright 2009-2024 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema;

use Garden\Utils\ArrayUtils;
use ReflectionClass;
use ReflectionProperty;

/**
 * Bidirectional field name mapping for an Entity class.
 *
 * Built once per class from reflection and cached statically in Entity.
 * Contains mappings between canonical property names and their primary alt names
 * (from PropertyAltNames), as well as MapSubProperties attribute instances for
 * sub-property field name derivation and reverse mapping.
 *
 * Usage:
 * ```php
 * $map = EntityFieldNameMap::build(MyEntity::class);
 * $altName = $map->convertFieldName('propertyName', EntityFieldFormat::PrimaryAltName);
 * $altArray = $map->reverseMapToAlt($canonicalArray);
 * ```
 */
class EntityFieldNameMap {
    /**
     * @param array<string, string> $canonicalToAlt Canonical name → primary alt name.
     *        Includes entries from PropertyAltNames (property name → alt name) and
     *        MapSubProperties (targetProperty.key → key, targetProperty.targetPath → sourcePath).
     * @param array<string, string> $altToCanonical Primary alt name → canonical name (reverse of canonicalToAlt).
     * @param array<string, bool> $altNameDotNotation Canonical property name → whether dot notation is used.
     *        Only populated from PropertyAltNames attributes.
     * @param array<string, MapSubProperties> $subPropertyMappings Target property name → MapSubProperties instance.
     */
    public function __construct(
        public readonly array $canonicalToAlt,
        public readonly array $altToCanonical,
        public readonly array $altNameDotNotation,
        public readonly array $subPropertyMappings,
    ) {}

    /**
     * Build a field name map from reflection for the given Entity class.
     *
     * Scans all public, non-static, non-excluded properties for PropertyAltNames
     * and MapSubProperties attributes to build bidirectional name mappings.
     *
     * @param class-string $class The entity class to build the map for.
     * @return self
     */
    public static function build(string $class): self {
        $reflection = new ReflectionClass($class);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

        $canonicalToAlt = [];
        $altToCanonical = [];
        $altNameDotNotation = [];
        $subPropertyMappings = [];

        foreach ($properties as $property) {
            if ($property->isStatic()) {
                continue;
            }
            if (!empty($property->getAttributes(ExcludeFromSchema::class))) {
                continue;
            }

            $name = $property->getName();

            // Collect PropertyAltNames mappings
            $altNameAttrs = $property->getAttributes(PropertyAltNames::class);
            if (!empty($altNameAttrs)) {
                /** @var PropertyAltNames $attr */
                $attr = $altNameAttrs[0]->newInstance();
                $primaryAltName = $attr->getPrimaryAltName();
                if ($primaryAltName !== null) {
                    $canonicalToAlt[$name] = $primaryAltName;
                    $altToCanonical[$primaryAltName] = $name;
                    $altNameDotNotation[$name] = $attr->useDotNotation();
                }
            }

            // Collect MapSubProperties instances and derive field name mappings
            $mapSubAttrs = $property->getAttributes(MapSubProperties::class);
            if (!empty($mapSubAttrs)) {
                /** @var MapSubProperties $attr */
                $attr = $mapSubAttrs[0]->newInstance();
                if (!empty($attr->getKeys()) || !empty($attr->getMapping())) {
                    $subPropertyMappings[$name] = $attr;

                    $entries = $attr->deriveFieldNameEntries($name);
                    $canonicalToAlt = array_merge($canonicalToAlt, $entries['canonicalToAlt']);
                    $altToCanonical = array_merge($altToCanonical, $entries['altToCanonical']);
                }
            }
        }

        return new self($canonicalToAlt, $altToCanonical, $altNameDotNotation, $subPropertyMappings);
    }

    /**
     * Convert a single field name between canonical and primary alt name formats.
     *
     * If the field name has no mapping for the target format, the original name is returned unchanged.
     *
     * @param string $fieldName The field name to convert.
     * @param EntityFieldFormat $targetFormat The target format to convert to.
     * @return string The converted field name, or the original if no mapping exists.
     */
    public function convertFieldName(string $fieldName, EntityFieldFormat $targetFormat): string {
        return match ($targetFormat) {
            EntityFieldFormat::Canonical => $this->altToCanonical[$fieldName] ?? $fieldName,
            EntityFieldFormat::PrimaryAltName => $this->canonicalToAlt[$fieldName] ?? $fieldName,
        };
    }

    /**
     * Transform a canonical array into an alt-name array by reversing all mappings.
     *
     * Applies reverse MapSubProperties mappings first (extracting nested values back
     * to their original flat/scattered locations), then renames properties using
     * PropertyAltNames primary alt names.
     *
     * @param array $result The canonical array to transform.
     * @return array The transformed array with alt names and reversed sub-property mappings.
     */
    public function reverseMapToAlt(array $result): array {
        // Apply reverse MapSubProperties mappings first (extract from nested to root)
        foreach ($this->subPropertyMappings as $targetProperty => $attr) {
            if (!array_key_exists($targetProperty, $result)) {
                continue;
            }

            $nestedData = $result[$targetProperty];
            if (!is_array($nestedData) && !$nestedData instanceof \ArrayObject) {
                continue;
            }

            // Convert ArrayObject to array for processing
            if ($nestedData instanceof \ArrayObject) {
                $nestedData = $nestedData->getArrayCopy();
            }

            $attr->reverseMappings($nestedData, $result);

            // Remove the nested property from result (it was constructed, not original)
            unset($result[$targetProperty]);
        }

        // Apply reverse PropertyAltNames mappings (rename properties to alt names)
        foreach ($this->canonicalToAlt as $propertyName => $primaryAltName) {
            if (!array_key_exists($propertyName, $result)) {
                continue;
            }

            $useDotNotation = $this->altNameDotNotation[$propertyName] ?? true;
            $value = $result[$propertyName];

            // Remove the main property name
            unset($result[$propertyName]);

            // Set the value at the alt name location
            if ($useDotNotation && str_contains($primaryAltName, '.')) {
                ArrayUtils::setByPath($primaryAltName, $result, $value);
            } else {
                $result[$primaryAltName] = $value;
            }
        }

        return $result;
    }
}
