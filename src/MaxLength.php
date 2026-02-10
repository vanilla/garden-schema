<?php
/**
 * @author Adam Charron <adam.c@vanillaforums.com>
 * @copyright 2009-2024 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema;

/**
 * Attribute to set the maximum length constraint on a string property.
 *
 * Example:
 * ```
 * #[MaxLength(100)]
 * public string $name;
 * ```
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::IS_REPEATABLE)]
class MaxLength extends PropertySchema {
    /**
     * @param int $maxLength The maximum length of the string.
     */
    public function __construct(int $maxLength) {
        parent::__construct(['maxLength' => $maxLength]);
    }
}
