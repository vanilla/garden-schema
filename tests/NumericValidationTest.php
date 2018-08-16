<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema\Tests;

use Garden\Schema\Schema;
use Garden\Schema\ValidationException;
use PHPUnit\Framework\TestCase;

class NumericValidationTest extends TestCase {


    /**
     * Test multipleOf tests.
     *
     * @param int|float $value The value to test.
     * @param int|float $multipleOf The multiple of property.
     * @param bool $expected Whether or not the value should be valid.
     * @dataProvider provideMultipleOfTests
     */
    public function testMultipleOf($value, $multipleOf, bool $expected) {
        $sch = new Schema([
            'type' => 'number',
            'multipleOf' => $multipleOf
        ]);

        $valid = $sch->isValid($value);
        $this->assertSame($expected, $valid);
    }

    /**
     * Generate multipleOf tests.
     *
     * @return array Returns a data provider.
     */
    public function provideMultipleOfTests() {
        $r = [
            [1, 1, true],
            [3, 2, false],
            [5.5, 5, false],
            [5.5, 1.1, true],
        ];

        return $r;
    }

    /**
     * Test the maximum and exclusiveMaximum properties.
     *
     * @param int $max The maximum property.
     * @param bool $exclusive The exclusiveMaximum property.
     * @param bool $expected The expected valid result.
     * @dataProvider provideMaximumTests
     */
    public function testMaximum($max, bool $exclusive, bool $expected) {
        $sch = new Schema([
            'type' => 'integer',
            'maximum' => $max,
            'exclusiveMaximum' => $exclusive,
        ]);

        try {
            $sch->validate(5);
            $this->assertTrue($expected);
        } catch (ValidationException $ex) {
            $this->assertFalse($expected);
        }
    }

    /**
     * Generate maximum property test data.
     *
     * @return mixed Returns a data provider array.
     */
    public function provideMaximumTests() {
        $r = [
            [5, false, true],
            [4, false, false],
            [5, true, false],
            [6, true, true],
        ];

        return $r;
    }

    /**
     * Test the minimum and exclusiveMinimum properties.
     *
     * @param int $min The minimum property.
     * @param bool $exclusive The exclusiveMinimum property.
     * @param bool $expected The expected valid result.
     * @dataProvider provideMinimumTests
     */
    public function testMinimum($min, bool $exclusive, bool $expected) {
        $sch = new Schema([
            'type' => 'integer',
            'minimum' => $min,
            'exclusiveMinimum' => $exclusive,
        ]);

        try {
            $sch->validate(5);
            $this->assertTrue($expected);
        } catch (ValidationException $ex) {
            $this->assertFalse($expected);
        }
    }

    /**
     * Generate minimum property test data.
     *
     * @return mixed Returns a data provider array.
     */
    public function provideMinimumTests() {
        $r = [
            [5, false, true],
            [6, false, false],
            [5, true, false],
            [4, true, true],
        ];

        return $r;
    }
}
