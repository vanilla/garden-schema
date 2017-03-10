<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2017 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema;


/**
 * A singleton that represents an invalid value.
 *
 * The purpose of this class is to provide an alternative to **null** for invalid values when **null** could be considered
 * valid.
 */
class Invalid {
    private static $value;

    /**
     * Private constructor to enforce singleton.
     */
    private function __constructor() {
        // no-op
    }

    /**
     * Return the invalid value.
     *
     * @return Invalid Returns the invalid value.
     */
    public static function value() {
        if (self::$value === null) {
            self::$value = new Invalid();
        }
        return self::$value;
    }

    /**
     * Tests a value to see if it is invalid.
     *
     * @param mixed $value The value to test.
     * @return bool Returns **true** of the value is invalid or **false** otherwise.
     */
    public static function isInvalid($value) {
        return $value === self::value();
    }
}
