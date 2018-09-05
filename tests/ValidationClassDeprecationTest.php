<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema\Tests;

use Garden\Schema\Validation;

/**
 * Test some deprecations in the validation class.
 */
class ValidationClassDeprecationTest extends AbstractSchemaTest {
    /**
     * The main status has been renamed to main number.
     * @deprecated
     */
    public function testMainStatusToNumber() {
        $vld = new Validation();

        $this->expectErrorNumber(E_USER_DEPRECATED);
        $vld->setMainStatus(123);

        $this->expectErrorNumber(E_USER_DEPRECATED);
        $this->assertSame($vld->getMainCode(), $vld->getMainStatus());
    }

    /**
     * The status has been renamed to number.
     * @deprecated
     */
    public function testStatusToNumber() {
        $vld = new Validation();

        $this->expectErrorNumber(E_USER_DEPRECATED);
        $vld->setMainStatus(123);

        $this->expectErrorNumber(E_USER_DEPRECATED);
        $this->assertSame($vld->getCode(), $vld->getStatus());
    }

    /**
     * An integer error for options is deprecated.
     * @deprecated
     */
    public function testIntOptions() {
        $vld = new Validation();
        $this->expectErrorNumber(E_USER_DEPRECATED);
        $vld->addError('foo', 'bar', 123);
        $this->assertSame(123, $vld->getCode());
    }

    /**
     * Options must be an integer or array.
     *
     * @expectedException \InvalidArgumentException
     * @deprecated
     */
    public function testInvalidOptions() {
        $vld = new Validation();

        $vld->addError('foo', 'bar', 'invalid');
    }

    /**
     * An options of 'status' should be converted to 'number', but be deprecated.
     */
    public function testStatusToNumberOptions() {
        $vld = new Validation();

        $this->expectErrorNumber(E_USER_DEPRECATED);
        $vld->addError('foo', 'bar', ['status' => 456]);
        $this->assertSame(456, $vld->getCode());
    }
}
