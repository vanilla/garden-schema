<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema\Tests;

use Garden\Schema\Schema;
use Garden\Schema\Tests\Fixtures\ExtendedSchema;

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

        $this->assertEquals($expected, $schema->getSchemaArray());
    }

    /**
     * Test a basic nested object.
     */
    public function testBasicNested() {
        $schema = Schema::parse([
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
        $schema = Schema::parse([
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
     * @dataProvider provideTypesAndData
     */
    public function testRootSchemas($short, $type) {
        $schema = Schema::parse([":$short" => 'desc']);

        $expected = ['type' => $type, 'description' => 'desc'];
        $this->assertEquals($expected, $schema->getSchemaArray());
    }

    /**
     * Test defining the root with a schema array.
     */
    public function testDefineRoot() {
        $schema = Schema::parse([
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
     * Verify the current class is returned from parse calls in Schema subclasses.
     */
    public function testSubclassing() {
        $subclass = ExtendedSchema::parse([]);
        $this->assertInstanceOf(ExtendedSchema::class, $subclass);
    }

    /**
     * Test the ability to pass constructor arguments using the parse method.
     */
    public function testConstructorParameters() {
        $subclass = ExtendedSchema::parse([], 'DiscussionController', 'index', 'out');
        $this->assertEquals('DiscussionController', $subclass->controller);
        $this->assertEquals('index', $subclass->method);
        $this->assertEquals('out', $subclass->type);
    }

    /**
     * Test JSON schema format to type conversion (and back).
     *
     * @param array $arr The schema array.
     * @param array $json A expected JSON schema array.
     * @dataProvider provideFormatToTypeConversionTests
     */
    public function testTypeToFormatConversion($arr, $json) {
        $schema = new Schema($arr);
        $this->assertEquals($json, $schema->jsonSerialize());
    }

    /**
     * Provide JSON schema formats that are schema types.
     *
     * @return array Returns a data provider array.
     */
    public function provideFormatToTypeConversionTests() {
        $r = [
            'datetime' => [
                ['type' => 'datetime'],
                ['type' => 'string', 'format' => 'date-time'],
            ],
            'timestamp' => [
                ['type' => 'timestamp'],
                ['type' => 'integer', 'format' => 'timestamp'],
            ],
            'datetime|null' => [
                ['type' => ['datetime', 'null']],
                ['type' => ['string', 'null'], 'format' => 'date-time'],
            ],
        ];

        $result = $r;
        foreach ($r as $key => $value) {
            $result["nested $key"] = [
                ['type' => 'object', 'properties' => ['prop' => $value[0]]],
                ['type' => 'object', 'properties' => ['prop' => $value[1]]],
            ];
        }

        return $result;
    }

    /**
     * Test that field style.
     *
     * @param string $style The field style.
     * @param string $delimiter The array delimiter.
     * @dataProvider provideFieldStyles
     */
    public function testFieldStyle($style, $delimiter) {
        $sch = Schema::parse(['' => [
            'type' => 'array',
            'style' => $style,
            'items' => [
                'type' => 'integer'
            ]
        ]]);

        $arr = [1, 2, 3];

        $valid = $sch->validate(implode($delimiter, $arr));
        $this->assertEquals($arr, $valid);
    }

    /**
     * Provide test data for {@link testFieldStyle()}.
     *
     * @return array Returns a data provider.
     */
    public function provideFieldStyles() {
        $r = [
            'form' => ['form', ','],
            'spaceDelimited' => ['spaceDelimited', ' '],
            'pipeDelimited' => ['pipeDelimited', '|']
        ];

        return $r;
    }

    /**
     * Test validating a custom filter.
     */
    public function testCustomFilter() {
        $sch = Schema::parse(['foo:s', 'bar:i']);
        $sch->addFilter('foo', function ($v) {
            return $v.'!';
        });

        $valid = $sch->validate(['foo' => 'bar', 'bar' => 2]);

        $this->assertEquals(['foo' => 'bar!', 'bar' => 2], $valid);
    }
}
