<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema\Tests\Fixtures;

use Garden\Schema\Validation;

/**
 * A validation object for testing the translations.
 */
class TestValidation extends Validation {
    private $prefix;

    /**
     * TestValidation constructor.
     *
     * @param string $prefix The prefix for each translation.
     */
    public function __construct(string $prefix = '!') {
        $this->prefix = $prefix;
    }

    /**
     * {@inheritdoc}
     */
    public function translate(string $str): string {
        if (substr($str, 0, 1) === '@') {
            // This is a literal string that bypasses translation.
            return substr($str, 1);
        } else {
            return $this->prefix.parent::translate($str);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function formatValue($value): string {
        $r = parent::formatValue($value);
        if ($this->getTranslateFieldNames()) {
            $r = $this->translate($r);
        }
        return $r;
    }

    /**
     * Create a factory function for this class.
     *
     * @param string $prefix The prefix for the constructor.
     * @param bool $translateFieldNames Whether or not the validation object has the `translateFieldNames` property set.
     * @return \Closure Returns a factory function.
     */
    public static function createFactory(string $prefix = '!', bool $translateFieldNames = false) {
        return function () use ($prefix, $translateFieldNames) {
            $r = new static($prefix);
            $r->setTranslateFieldNames($translateFieldNames);

            return $r;
        };
    }
}
