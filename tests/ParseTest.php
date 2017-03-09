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
                'name' => ['type' => 'string', 'minLength' => 1, 'description' => 'The name of the object.'],
                'description' => ['type' => 'string'],
                'timestamp' => ['type' => 'timestamp'],
                'date' => ['type' => 'datetime'],
                'amount' => ['type' => 'number'],
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

    /**
     * Test single root schemas.
     *
     * @param string $short The short type to test.
     * @param string $type The type to test.
     * @dataProvider provideTypes
     */
    public function testRootSchemas($short, $type) {
        $schema = new Schema([":$short" => 'desc']);

        $expected = ['type' => $type, 'description' => 'desc'];
        $this->assertEquals($expected, $schema->jsonSerialize());
    }

    /**
     * Test defining the root with a schema array.
     */
    public function testDefineRoot() {
        $schema = new Schema([
            ':a' => [
                'userID:i',
                'name:s',
                'email:s'
            ]
        ]);

        $expected = [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'userID' => ['type' => 'integer'],
                    'name' => ['type' => 'string', 'minLength' => 1],
                    'email' => ['type' => 'string', 'minLength' => 1]
                ],
                'required' => ['userID', 'name', 'email']
            ]
        ];

        $this->assertEquals($expected, $schema->jsonSerialize());
    }

    /**
     * Test merging basic schemas.
     */
    public function testBasicMerge() {
        $schemaOne = new Schema(['foo:s?']);
        $schemaTwo = new Schema(['bar:s']);

        $schemaOne->merge($schemaTwo);

        $expected = [
            'type' => 'object',
            'properties' => [
                'foo' => ['type' => 'string'],
                'bar' => ['type' => 'string', 'minLength' => 1]
            ],
            'required' => ['bar']
        ];

        $this->assertEquals($expected, $schemaOne->jsonSerialize());
    }

    /**
     * Test merging nested schemas.
     */
    public function testNestedMerge() {
        $schemaOne = $this->getArrayOfObjectsSchema();
        $schemaTwo = new Schema([
            'rows:a' => [
                'email:s'
            ]
        ]);

        $expected = [
            'type' => 'object',
            'properties' => [
                'rows' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'name' => ['type' => 'string'],
                            'email' => ['type' => 'string', 'minLength' => 1]
                        ],
                        'required' => ['id', 'email']
                    ]
                ]
            ],
            'required' => ['rows']
        ];

        $schemaOne->merge($schemaTwo);

        $this->assertEquals($expected, $schemaOne->jsonSerialize());
    }
}
