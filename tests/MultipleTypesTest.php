<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2017 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema\Tests;
use Garden\Schema\Schema;

/**
 * Tests for schemas where the type property is multiple types.
 */
class MultipleTypesTest extends AbstractSchemaTest {
    /**
     * Provide test data for basic multiple type tests.
     *
     * @return array Returns a data provider.
     */
    public function provideBasicMultipleTypeTests() {
        $r = [
            ['integer array 1', 123],
            ['integer array 2', [1]],
            ['boolean string 1', true],
            ['boolean string 2', false],
            ['boolean string 3', 'true'],
            ['boolean string 4', ''],
            ['boolean integer 1', 1],
            ['boolean number 1', 1.234],
            ['boolean number 2', 1, 1.0],
            ['integer boolean 1', true],
            ['datetime string 1', 'today'],
            ['datetime string 2', '2010-01-01', new \DateTimeImmutable('2010-01-01')],
            ['integer number 1', 123],
            ['integer number 2', 123.4],
            ['number integer 1', 123],
            ['number integer 2', 123.4],
        ];

        return array_column($r, null, 0);
    }

    /**
     * Basic multiple type tests.
     *
     * @param string $types A space delimited string of type names.
     * @param mixed $value A value to test.
     * @param mixed $expected The expected valid value.
     * @dataProvider provideBasicMultipleTypeTests
     */
    public function testBasicMultipleTypes($types, $value, $expected = null) {
        $types = array_filter(explode(' ', $types), function ($v) {
            return !is_numeric($v);
        });

        $sch = new Schema([
            'type' => $types
        ]);

        $expected = $expected === null ? $value : $expected;

        $valid = $sch->validate($value);
        if (is_array($expected) || $expected instanceof \DateTimeInterface) {
            $this->assertEquals($expected, $valid);
        } else {
            $this->assertSame($expected, $valid);
        }
    }

    /**
     * Test a type and an array of that type.
     *
     * @param string $short The short code which is not used.
     * @param string $type The type to test.
     * @param mixed $value A valid value for the type.
     * @dataProvider provideTypesAndData
     */
    public function testTypeAndArrayOfType($short, $type, $value) {
        if ($type === 'array') {
            // Just return because this isn't really a valid test to skip.
            return;
//            $this->markTestSkipped('An array is invalid for this test.');
        }

        $sch = new Schema([
            'type' => [
                $type,
                'array'
            ],
            'items' => [
                'type' => $type
            ]
        ]);

        $valid = $sch->validate($value);
        $this->assertSame($value, $valid);

        $arrayValue = [$value, $value, $value];
        $arrayValid = $sch->validate($arrayValue);
        $this->assertSame($arrayValue, $arrayValid);
    }

    /**
     * Strings that are expanded with the **style** property should have some fidelity to an alternative type.
     *
     * This is to help supporting the "expand" parameter that Vanilla APIs can take where the value can be true or an array of field to expand.
     *
     * @param mixed $value The value to test.
     * @param mixed $expected The expected valid data.
     * @dataProvider provideExpandUserCaseTests
     */
    public function testExpandUseCase($value, $expected) {
        $sch = new Schema([
            'type' => [
                'boolean',
                'array'
            ],
            'style' => 'form',
            'items' => [
                'type' => 'string'
            ]
        ]);

        $valid = $sch->validate($value);
        $this->assertSame($expected, $valid);
    }

    /**
     * Provide tests for **testExpandUseCase()**.
     *
     * @return array Returns a data provider array.
     */
    public function provideExpandUserCaseTests() {
        $r = [
            ['true', true],
            ['1', true],
            ['false', false],
            ['0', false],
            ['a,b,c', ['a', 'b', 'c']]
        ];

        return array_column($r, null, 0);
    }
}
