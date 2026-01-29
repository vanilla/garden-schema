<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema;

/**
 * Attribute to customize the schema for an entity property.
 *
 * The provided schema array is merged with the auto-generated schema from the property's
 * type information. This allows you to add constraints (minLength, maxLength, pattern, etc.)
 * or override auto-generated values while preserving type inference.
 *
 * Example:
 * ```
 * #[PropertySchema(['minLength' => 1, 'maxLength' => 100])]
 * public string $name;  // Auto-generates type: string, merges with minLength/maxLength
 *
 * #[PropertySchema(['items' => ['type' => 'string']])]
 * public array $tags;  // Auto-generates type: array, adds items constraint
 * ```
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class PropertySchema {
    /**
     * @var array
     */
    private array $schema;

    /**
     * @param array $schema A partial schema array to merge with auto-generated schema.
     *                      Values in this array override auto-generated values.
     */
    public function __construct(array $schema = []) {
        $this->schema = $schema;
    }

    /**
     * @return array Returns the schema array for the property.
     */
    public function getSchema(): array {
        return $this->schema;
    }
}
