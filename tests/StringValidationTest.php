<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2017 Vanilla Forums Inc.
 * @license Proprietary
 */

namespace Garden\Schema\Tests;

use Garden\Schema\Schema;
use Garden\Schema\Validation;
use Garden\Schema\ValidationException;

/**
 * Test string validation properties.
 */
class StringValidationTest extends AbstractSchemaTest {
    /**
     * Test string min length constraints.
     *
     * @param string $str The string to test.
     * @param string $code The expected error code, if any.
     * @param int $minLength The min length to test.
     * @dataProvider provideMinLengthTests
     */
    public function testMinLength($str, $code, $minLength = 3) {
        $schema = new Schema(['str:s' => ['minLength' => $minLength]]);

        try {
            $schema->validate(['str' => $str]);

            if (!empty($code)) {
                $this->fail("'$str' shouldn't validate against a min length of $minLength.");
            }
        } catch (ValidationException $ex) {
            $this->assertFieldHasError($ex->getValidation(), 'str', $code);
        }
    }

    /**
     * Provide test data for {@link testMinLength()}.
     *
     * @return array Returns a data provider array.
     */
    public function provideMinLengthTests() {
        $r = [
            'empty' => ['', 'minLength'],
            'ab' => ['ab', 'minLength'],
            'abc' => ['abc', ''],
            'abcd' => ['abcd', ''],

            'empty 1' => ['', 'missingField', 1],
            'empty 0' => ['', '', 0]
        ];

        return $r;
    }

    /**
     * Test string max length constraints.
     *
     * @param string $str The string to test.
     * @param string $code The expected error code, if any.
     * @param int $maxLength The max length to test.
     * @dataProvider provideMaxLengthTests
     */
    public function testMaxLength($str, $code = '', $maxLength = 3) {
        $schema = new Schema(['str:s?' => ['maxLength' => $maxLength]]);

        try {
            $schema->validate(['str' => $str]);

            if (!empty($code)) {
                $this->fail("'$str' shouldn't validate against a max length of $maxLength.");
            }
        } catch (ValidationException $ex) {
            $this->assertFieldHasError($ex->getValidation(), 'str', $code);
        }
    }

    /**
     * Provide test data for {@link testMaxLength()}.
     *
     * @return array Returns a data provider array.
     */
    public function provideMaxLengthTests() {
        $r = [
            'empty' => [''],
            'ab' => ['ab'],
            'abc' => ['abc'],
            'abcd' => ['abcd', 'maxLength'],
        ];

        return $r;
    }

    /**
     * Test string pattern constraints.
     *
     * @param string $str The string to test.
     * @param string $code The expected error code, if any.
     * @param string $pattern The pattern to test.
     * @dataProvider providePatternTests
     */
    public function testPattern($str, $code = '', $pattern = '^[a-z]o+$') {
        $schema = new Schema(['str:s?' => ['pattern' => $pattern]]);

        try {
            $schema->validate(['str' => $str]);

            if (!empty($code)) {
                $this->fail("'$str' shouldn't validate against a pattern of $pattern.");
            }
        } catch (ValidationException $ex) {
            $this->assertFieldHasError($ex->getValidation(), 'str', $code);
        }
    }

    /**
     * Provide test data for {@link testPattern()}.
     *
     * @return array Returns a data provider array.
     */
    public function providePatternTests() {
        $r = [
            'empty' => ['', 'invalid'],
            'fo' => ['fo', ''],
            'foo' => ['foooooooooo', ''],
            'abcd' => ['abcd', 'invalid'],
        ];

        return $r;
    }

    /**
     * Test the enum constraint.
     *
     * @expectedException \Garden\Schema\ValidationException
     * @expectedExceptionMessage value must be one of: one, two, three.
     * @expectedExceptionCode 422
     */
    public function testEnum() {
        $enum = ['one', 'two', 'three'];
        $schema = new Schema([':s' => ['enum' => $enum]]);

        foreach ($enum as $str) {
            $this->assertTrue($schema->isValid($str));
        }

        $schema->validate('four');
    }

    /**
     * Test a required empty string with a min length of 0.
     */
    public function testRequiredEmptyString() {
        $schema = new Schema([
            'col:s' => ['minLength' => 0]
        ]);

        $emptyData = ['col' => ''];
        $valid = $schema->validate($emptyData);
        $this->assertEmpty($valid['col']);
        $this->assertInternalType('string', $valid['col']);

        $nullData = ['col' => null];
        $isValid = $schema->isValid($nullData);
        $this->assertFalse($isValid);

        $missingData = [];
        $isValid = $schema->isValid($missingData);
        $this->assertFalse($isValid);
    }
}
