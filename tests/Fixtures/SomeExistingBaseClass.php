<?php
/**
 * @author Adam Charron <adam@vanillaforums.com>
 * @copyright 2009-2024 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema\Tests\Fixtures;

/**
 * A simple base class to demonstrate that EntityInterface can be implemented
 * by classes that already have their own parent class.
 */
class SomeExistingBaseClass {
    /**
     * Some method from the base class.
     */
    public function baseClassMethod(): string {
        return 'from base class';
    }
}
