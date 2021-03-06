<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema\Tests;

use Garden\Schema\Schema;

/**
 * Tests specific to arrays.
 */
class ArrayValidationTest extends AbstractSchemaTest {
    /**
     * Test the maxItems property for arrays.
     */
    public function testMinItems() {
        $sch = new Schema(['type' => 'array', 'minItems' => 1]);

        $this->assertTrue($sch->isValid([1]));
        $this->assertTrue($sch->isValid([1, 2]));
        $this->assertFalse($sch->isValid([]));
    }

    /**
     * Test the minItems property for arrays.
     */
    public function testMaxItems() {
        $sch = new Schema(['type' => 'array', 'maxItems' => 2]);

        $this->assertTrue($sch->isValid([1]));
        $this->assertTrue($sch->isValid([1, 2]));
        $this->assertFalse($sch->isValid([1, 2, 3]));
    }

    /**
     * Test the uniqueItems property for arrays.
     *
     * @param array $value The value to test.
     * @param bool $expected The expected valid result.
     * @dataProvider provideUniqueItemsTests
     */
    public function testUniqueItems(array $value, bool $expected) {
        $sch = new Schema(['type' => 'array', 'uniqueItems' => true]);

        $valid = $sch->isValid($value);
        $this->assertSame($expected, $valid);
    }

    /**
     * Provide uniqueItems tests.
     *
     * @return array Returns a data provider.
     */
    public function provideUniqueItemsTests() {
        $r = [
            [[], true],
            [[1, 2], true],
            [[1, 2, 1], false],
        ];

        return $r;
    }
}
