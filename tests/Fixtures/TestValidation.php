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
    /**
     * {@inheritdoc}
     */
    protected function translate($str) {
        return '!'.parent::translate($str);
    }
}
