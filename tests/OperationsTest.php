<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2017 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema\Tests;


use Garden\Schema\Schema;

class OperationsTest extends AbstractSchemaTest {
    /**
     * Test merging basic schemas.
     */
    public function testBasicMerge() {
        $schemaOne = Schema::parse(['foo:s?']);
        $schemaTwo = Schema::parse(['bar:s']);

        $schemaOne->merge($schemaTwo);

        $expected = [
            'type' => 'object',
            'properties' => [
                'foo' => ['type' => 'string'],
                'bar' => ['type' => 'string', 'minLength' => 1]
            ],
            'required' => ['bar']
        ];

        $this->assertEquals($expected, $schemaOne->getSchemaArray());
    }

    /**
     * Test merging nested schemas.
     */
    public function testNestedMerge() {
        $schemaOne = $this->getArrayOfObjectsSchema();
        $schemaTwo = Schema::parse([
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

    /**
     * Test schema adding.
     */
    public function testAdd() {
        $sc1 = Schema::parse(['a:o?' => ['b?', 'c']]);
        $sc2 = Schema::parse(['a:o' => ['b:i', 'e'], 'd']);

        $expected = [
            'type' => 'object',
            'properties' => [
                'a' => [
                    'type' => 'object',
                    'properties' => [
                        'b' => ['type' => 'integer'],
                        'c' => []
                    ],
                    'required' => ['c']
                ]
            ]
        ];

        $actual = $sc1->add($sc2)->getSchemaArray();

        $this->assertEquals($expected, $actual);
    }

    /**
     * Test adding with adding properties.
     */
    public function testAddWithProperties() {
        $sc1 = Schema::parse(['a:s?', 'b?', 'c?']);
        $sc2 = Schema::parse(['a:i', 'b:s?', 'c?' => 'Description', 'd']);

        $expected = [
            'type' => 'object',
            'properties' => [
                'a' => ['type' => 'string'],
                'b' => ['type' => 'string'],
                'c' => ['description' => 'Description'],
                'd' => []
            ],
            'required' => ['d']
        ];

        $actual = $sc1->add($sc2, true)->getSchemaArray();

        $this->assertEquals($expected, $actual);
    }

    /**
     * Test adding with an enum.
     */
    public function testAddEnum() {
        $sch1 = Schema::parse([':s' => ['enum' => ['a', 'b']]]);
        $sch2 = Schema::parse([':s' => ['enum' => ['a', 'c']]]);

        $expected = [
            'type' => 'string',
            'enum' => ['a', 'b']
        ];

        $actual = $sch1->add($sch2)->getSchemaArray();
        $this->assertEquals($expected, $actual);

        $actual2 = $sch1->add($sch2, true)->getSchemaArray();
        $this->assertEquals($expected, $actual2);
    }

    /**
     * Test merging with an enum.
     */
    public function testMergeEnum() {
        $sch1 = Schema::parse([':s' => ['enum' => ['a', 'b']]]);
        $sch2 = Schema::parse([':s' => ['enum' => ['a', 'c']]]);

        $expected = [
            'type' => 'string',
            'enum' => ['a', 'b', 'c']
        ];

        $actual = $sch1->merge($sch2)->getSchemaArray();
        $this->assertEquals($expected, $actual);
    }

    /**
     * Test turning a schema into a sparse schema.
     */
    public function testWithSparse() {
        $sch1 = Schema::parse([
            'a:o' => ['b:s', 'c:i'],
            'b:a' => ['b:s', 'c:i']
        ]);
        $sch2 = $sch1->withSparse();

        $data = [];
        $result = $sch2->validate($data);
        $this->assertSame($data, $result);

        $data2 = ['a' => ['c' => 1], 'b' => [['b' => 'foo']]];
        $result2 = $sch2->validate($data2);
        $this->assertSame($data2, $result2);
    }

    /**
     * Test making sparse schemas with duplicate schemas.
     */
    public function testWithSparseSchemaReuse() {
        $sch1 = Schema::parse(['id:i', 'name:s']);
        $sch2 = Schema::parse([
            'u1' => $sch1,
            'u2' => $sch1
        ]);

        $sch3 = $sch2->withSparse();

        $this->assertSame($sch3->getField('properties.u1'), $sch3->getField('properties.u2'));

        $data = [
            'u1' => ['id' => 1],
            'u2' => ['name' => 'Frank']
        ];

        $valid = $sch3->validate($data);
        $this->assertEquals($data, $valid);
    }
}
