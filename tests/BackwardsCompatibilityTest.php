<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema\Tests;

use Garden\Schema\Schema;
use Garden\Schema\Tests\Fixtures\TestValidation;
use Garden\Schema\ValidationException;


/**
 * Test some backwards compatibility with deprecated behavior.
 */
class BackwardsCompatibilityTest extends AbstractSchemaTest {

    /**
     * Test field reference compat.
     *
     * @param string $old The old field selector.
     * @param string $new The expected new field selector.
     * @dataProvider provideFieldSelectorConversionTests
     */
    public function testFieldSelectorConversion(string $old, string $new) {
        $this->expectErrorNumber(E_USER_DEPRECATED, false);
        $this->assertFieldSelectorConversion($old, $new);
    }

    /**
     * Return field conversion tests.
     *
     * @return array Returns a data provider.
     */
    public function provideFieldSelectorConversionTests() {
        $r = [
            ['', ''],
            ['backgroundColor', 'properties/backgroundColor'],
            ['foo.bar', 'properties/foo/properties/bar'],
            ['foo[]', 'properties/foo/items'],
            ['[]', 'items'],
        ];

        return array_column($r, null, 0);
    }

    /**
     * New field selectors should not trigger deprecation errors.
     *
     * @param string $field The field selector to test.
     * @dataProvider provideFieldSelectorNonConversionTests
     */
    public function testFieldSelectorNonConversion(string $field) {
        $this->assertFieldSelectorConversion($field, $field);
    }

    /**
     * Return field non-conversion tests.
     *
     * None of these tests should throw deprecation errors.
     *
     * @return array Returns a data provider.
     */
    public function provideFieldSelectorNonConversionTests() {
        $r = [
            ['items'],
            ['additionalProperties'],
            ['properties/foo']
        ];

        return array_column($r, null, 0);
    }

    /**
     * Dot separators should still work with `Schemna::getField()`, but trigger a deprecated error.
     */
    public function testGetFieldSeparators() {
        $sch = new Schema([
            'type' => 'object',
            'properties' => [
                'foo' => [
                    'type' => 'integer',
                ],
            ],
        ]);

        $this->expectErrorNumber(E_USER_DEPRECATED);
        $type = $sch->getField('properties.foo.type');
        $this->assertEquals('integer', $type);
    }

    /**
     * Dot separators should still work with `Schema::setField()`, but trigger a deprecated error.
     */
    public function testSetFieldSeparators() {
        $sch = new Schema([
            'type' => 'object',
            'properties' => [
                'foo' => [
                    'type' => 'integer',
                ],
            ],
        ]);

        $this->expectErrorNumber(E_USER_DEPRECATED);
        $sch->setField('properties.foo.type', 'string');
        $type = $sch->getField('properties/foo/type');
        $this->assertEquals('string', $type);
    }

    /**
     * Assert a field selector conversion.
     *
     * @param string $old The old field selector.
     * @param string $new The new field selector that is expected.
     */
    public function assertFieldSelectorConversion(string $old, string $new) {
        $sch = new Schema();
        $fn = function ($field) {
            return $this->parseFieldSelector($field);
        };
        $fn = $fn->bindTo($sch, Schema::class);

        $actual = $fn($old);
        $this->assertEquals($new, $actual);
    }

    /**
     * Test a custom validation class.
     */
    public function testDifferentValidationClass() {
        $schema = Schema::parse([':i']);
        $this->expectErrorNumber(E_USER_DEPRECATED);
        $schema->setValidationClass(TestValidation::class);

        $this->expectErrorNumber(E_USER_DEPRECATED);
        $this->assertSame(TestValidation::class, $schema->getValidationClass());

        try {
            $schema->validate('aaa');
        } catch (ValidationException $ex) {
            $this->assertSame('!"aaa" is not a valid integer.', $ex->getMessage());
        }

        $validation = new TestValidation();
        $this->expectErrorNumber(E_USER_DEPRECATED);
        $schema->setValidationClass($validation);
        try {
            $schema->validate('aaa');
        } catch (ValidationException $ex) {
            $this->assertSame('!"aaa" is not a valid integer.', $ex->getMessage());
        }

        $validation->setTranslateFieldNames(true);
        try {
            $schema->validate('aaa');
        } catch (ValidationException $ex) {
            $this->assertSame('!!"aaa" is not a valid integer.', $ex->getMessage());
        }
    }

    /**
     * Test validating the null type.
     */
    public function testNullValidation() {
        $sch = new Schema(['type' => 'null']);

        $valid = $sch->validate(null);
        $this->assertNull($valid);

        $this->expectException(ValidationException::class);
        $valid = $sch->validate(123);
    }
}
