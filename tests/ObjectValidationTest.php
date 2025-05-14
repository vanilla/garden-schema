<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema\Tests;

use Garden\Schema\Schema;
use Garden\Schema\ValidationException;

class ObjectValidationTest extends AbstractSchemaTest {
    /**
     * Test the maxProperties property.
     */
    public function testMaxProperties() {
        $sch = new Schema([
            'type' => 'object',
            'maxProperties' => 1
        ]);

        $this->assertTrue($sch->isValid(['a' => 1]));
        $this->assertFalse($sch->isValid(['a' => 1, 'b' => 2]));
    }

    /**
     * Test the minProperties property.
     */
    public function testMinProperties() {
        $sch = new Schema([
            'type' => 'object',
            'minProperties' => 2
        ]);

        $this->assertTrue($sch->isValid(['a' => 1, 'b' => 2]));
        $this->assertFalse($sch->isValid(['a' => 1]));
    }

    /**
     * Test the additionalProperties validator without a properties validator.
     */
    public function testAdditionalProperties() {
        $sch = new Schema([
            'type' => 'object',
            'additionalProperties' => [
                'type' => 'boolean',
            ],
        ]);

        $valid = $sch->validate(['a' => 'false', 'B' => 'true']);
        $this->assertEquals(['a' => false, 'B' => true], $valid);
    }

    /**
     * Test properties and additionalProperties together.
     */
    public function testPropertiesWithAdditionalProperties() {
        $sch = new Schema([
            'type' => 'object',
            'properties' => [
                'b' => [
                    'type' => 'string',
                ],
            ],
            'additionalProperties' => [
                'type' => 'boolean',
            ],
        ]);

        $valid = $sch->validate(['A' => 'false', 'b' => 'true']);
        $this->assertEquals(['A' => false, 'b' => 'true'], $valid);
    }

    /**
     * Additional Properties of **true** should always validate.
     */
    public function testTrueAdditionalProperties() {
        $sch = new Schema([
            'type' => 'object',
            'properties' => [
                'b' => [
                    'type' => 'string',
                ],
            ],
            'additionalProperties' => true,
        ]);

        $valid = $sch->validate(['A' => 'false', 'b' => 'true']);
        $this->assertEquals(['A' => 'false', 'b' => 'true'], $valid);
    }

    /**
     * Properties that have special characters should show up as escaped in errors.
     */
    public function testPropertyEscapingErrors() {
        $sch = new Schema([
            'type' => 'object',
            'properties' => [
                '~/' => [
                    'type' => 'integer',
                ],
            ],
        ]);

        try {
            $valid = $sch->validate(['~/' => 123.4]);
            $this->fail("There should be a validation exception.");
        } catch (ValidationException $ex) {
            $this->assertSame('value: 123.4 is not a valid integer.', $ex->getValidation()->getConcatMessage('~0~1'));
        }
    }
}
