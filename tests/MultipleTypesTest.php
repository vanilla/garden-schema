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
            ['boolean string 1', false],
            ['boolean string 1', 'true'],
            ['boolean string 1', ''],
            ['boolean integer 1', 1],
            ['datetime string 1', 'today']
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
        if (is_array($expected)) {
            $this->assertEquals($expected, $valid);
        } else {
            $this->assertSame($expected, $valid);
        }
    }
}
