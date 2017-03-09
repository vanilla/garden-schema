<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2017 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema\Tests\Fixtures;

use Garden\Schema\Validation;

/**
 * A validation object for testing the translations.
 */
class TestValidation extends Validation {
    private $prefix;

    public function __construct($prefix = '!') {
        $this->prefix = $prefix;
    }

    /**
     * {@inheritdoc}
     */
    public function translate($str) {
        if (substr($str, 0, 1) === '@') {
            // This is a literal string that bypasses translation.
            return substr($str, 1);
        } else {
            return $this->prefix.parent::translate($str);
        }
    }
}
