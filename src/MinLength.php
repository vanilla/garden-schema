<?php
/**
 * @author Adam Charron <adam.c@vanillaforums.com>
 * @copyright 2009-2024 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema;

/**
 * Attribute to set the minimum length constraint on a string property.
 *
 * Example:
 * ```
 * #[MinLength(1)]
 * public string $name;
 * ```
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::IS_REPEATABLE)]
class MinLength extends PropertySchema {
    /**
     * @param int $minLength The minimum length of the string.
     */
    public function __construct(int $minLength) {
        parent::__construct(['minLength' => $minLength]);
    }
}
