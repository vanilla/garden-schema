<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2017 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema\Tests;

use Garden\Schema\Schema;

/**
 * Test schema parsing.
 */
class ParseTest extends AbstractSchemaTest {
    /**
     * Test the basic atomic types in a schema.
     */
    public function testAtomicTypes() {
        $schema = $this->getAtomicSchema();

        $expected = [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'required' => true],
                'name' => ['type' => 'string', 'required' => true, 'description' => 'The name of the object.'],
                'description' => ['type' => 'string'],
                'timestamp' => ['type' => 'timestamp'],
                'date' => ['type' => 'datetime'],
                'amount' => ['type' => 'float'],
                'enabled' => ['type' => 'boolean'],
            ]
        ];

        $this->assertEquals($expected, $schema->jsonSerialize());
    }

    /**
     * Test a basic nested object.
     */
    public function testBasicNested() {
        $schema = Schema::create([
            'obj:o' => [
                'id:i',
                'name:s?'
            ]
        ]);

        $expected = [
            'obj' => [
                'type' => 'object',
                'required' => true,
                'properties' => [
                    'id' => ['type' => 'integer', 'required' => true],
                    'name' => ['type' => 'string']
                ]
            ]
        ];

        $actual = $schema->jsonSerialize();
        $this->assertEquals($expected, $actual['properties']);
    }

    /**
     * Test to see if a nested schema can be used to create an identical nested schema.
     */
    public function testNestedLongForm() {
        $schema = $this->getNestedSchema();

        // Make sure the long form can be used to create the schema.
        $schema2 = new Schema($schema->jsonSerialize());
        $this->assertEquals($schema->jsonSerialize(), $schema2->jsonSerialize());
    }

    /**
     * Test a double nested schema.
     */
    public function testDoubleNested() {
        $schema = Schema::create([
            'obj:o' => [
                'obj:o?' => [
                    'id:i'
                ]
            ]
        ]);

        $expected = [
            'obj' => [
                'type' => 'object',
                'required' => true,
                'properties' => [
                    'obj' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => [
                                'type' => 'integer',
                                'required' => true
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $this->assertEquals($expected, $schema->jsonSerialize()['properties']);
    }
}
