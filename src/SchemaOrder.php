<?php
/**
 * @author Adam Charron <adam.c@vanillaforums.com>
 * @copyright 2009-2024 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema;

/**
 * Attribute to control the ordering of properties in the generated schema.
 *
 * Properties with SchemaOrder come first in the schema, sorted by their order
 * value in ascending order. Properties without SchemaOrder retain their original
 * declaration order and appear after all ordered properties.
 *
 * Example:
 * ```
 * class MyEntity extends Entity {
 *     #[SchemaOrder(2)]
 *     public string $second;
 *
 *     #[SchemaOrder(1)]
 *     public string $first;
 *
 *     public string $unordered;
 * }
 * // Schema property order: first, second, unordered
 * ```
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class SchemaOrder {
    public function __construct(
        private int $order,
    ) {}

    /**
     * Get the order value for this property.
     *
     * @return int
     */
    public function getOrder(): int {
        return $this->order;
    }
}
