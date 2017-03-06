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
                'id' => ['type' => 'integer'],
                'name' => ['type' => 'string', 'description' => 'The name of the object.'],
                'description' => ['type' => 'string'],
                'timestamp' => ['type' => 'timestamp'],
                'date' => ['type' => 'datetime'],
                'amount' => ['type' => 'float'],
                'enabled' => ['type' => 'boolean'],
            ],
            'required' => ['id', 'name']
        ];

        $this->assertEquals($expected, $schema->jsonSerialize());
    }

    /**
     * Test a basic nested object.
     */
    public function testBasicNested() {
        $schema = new Schema([
            'obj:o' => [
                'id:i',
                'name:s?'
            ]
        ]);

        $expected = [
            'type' => 'object',
            'properties' => [
                'obj' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'name' => ['type' => 'string']
                    ],
                    'required' => ['id']
                ]
            ],
            'required' => ['obj']
        ];

        $actual = $schema->jsonSerialize();
        $this->assertEquals($expected, $actual);
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
        $schema = new Schema([
            'obj:o' => [
                'obj:o?' => [
                    'id:i'
                ]
            ]
        ]);

        $expected = [
            'type' => 'object',
            'properties' => [
                'obj' => [
                    'type' => 'object',
                    'properties' => [
                        'obj' => [
                            'type' => 'object',
                            'properties' => [
                                'id' => [
                                    'type' => 'integer'
                                ]
                            ],
                            'required' => ['id']
                        ]
                    ]
                ]
            ],
            'required' => ['obj']
        ];

        $this->assertEquals($expected, $schema->jsonSerialize());
    }
}
