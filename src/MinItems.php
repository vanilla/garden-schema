<?php
/**
 * @author Adam Charron <adam.c@vanillaforums.com>
 * @copyright 2009-2024 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema;

/**
 * Attribute to set the minimum items constraint on an array property.
 *
 * Example:
 * ```
 * #[MinItems(1)]
 * public array $tags;
 * ```
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::IS_REPEATABLE)]
class MinItems extends PropertySchema {
    /**
     * @param int $minItems The minimum number of items in the array.
     */
    public function __construct(int $minItems) {
        parent::__construct(['minItems' => $minItems]);
    }
}
