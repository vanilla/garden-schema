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
 * This attribute is repeatable, allowing multiple schema customizations to be stacked.
 * Multiple attributes are merged in order using Schema::merge().
 *
 * This attribute can be subclassed to create reusable schema helpers (e.g., #[MinLength(1)]).
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
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::IS_REPEATABLE)]
class PropertySchema implements PropertySchemaInterface {
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
     * @return Schema Returns the schema for the property.
     */
    public function getSchema(): Schema {
        return new Schema($this->schema);
    }
}
