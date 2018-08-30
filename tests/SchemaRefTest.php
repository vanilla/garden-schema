<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema\Tests;

use Garden\Schema\ArrayRefLookup;
use Garden\Schema\RefNotFoundException;
use Garden\Schema\Schema;
use PHPUnit\Framework\TestCase;

class SchemaRefTest extends TestCase {
    /**
     * @var array A big array with multiple schemas and references.
     */
    private $sch;

    /**
     * @var ArrayRefLookup
     */
    private $lookup;

    /**
     * Create test data with every test.
     */
    public function setUp() {
        parent::setUp();

        $this->sch = [
            'ref' => [
                '$ref' => '#/schemas/value',
            ],
            'ref-ref' => [
                '$ref' => '#/schemas/tuple',
            ],
            'items' => [
                'type' => 'array',
                'items' => [
                    '$ref' => '#/schemas/value',
                ],
            ],
            'additionalProperties' => [
                'type' => 'object',
                'additionalProperties' => [
                    '$ref' => '#/schemas/value',
                ],
            ],
            'cycle' => [
                '$ref' => '#/schemas/cycle',
            ],
            'category' => [
                'type' => 'object',
                'properties' => [
                    'name' => [
                        'type' => 'string',
                    ],
                    'children' => [
                        'type' => 'array',
                        'items' => [
                            '$ref' => '#/category',
                        ],
                    ],
                ],
                'required' => ['name'],
            ],
            'nowhere' => [
                '$ref' => '#/oehroieqhwr',
            ],
            'local-ref' => [
                '$ref' => '#foo',
            ],
            'schemas' => [
                'tuple' => [
                    'type' => 'object',
                    'properties' => [
                        'key' => [
                            'type' => 'string',
                        ],
                        'value' => [
                            '$ref' => '#/schemas/value'
                        ],
                    ],
                    'required' => ['key', 'value'],
                ],
                'value' => [
                    'type' => 'integer',
                ],
                'cycle' => [
                    '$ref' => '#/cycle',
                ],
            ],
        ];

        $this->lookup = new ArrayRefLookup($this->sch);
    }

    /**
     * Test a schema that is just a reference to another schema.
     */
    public function testRootRef() {
        $sch = $this->schema('ref');

        $valid = $sch->validate('123');
        $this->assertSame(123, $valid);
    }

    /**
     * Test a schema ref that itself includes another ref.
     */
    public function testRefRef() {
        $sch = $this->schema('ref-ref');

        $valid = $sch->validate(['key' => 123, 'value' => '123']);
        $this->assertSame(['key' => '123', 'value' => 123], $valid);
    }

    /**
     * Test a schema with array items that are a ref.
     */
    public function testItemsRef() {
        $sch = $this->schema('items');

        $valid = $sch->validate(['1', '2', 3.0]);
        $this->assertSame([1, 2, 3], $valid);
    }

    /**
     * Objects should allow references in additional properties.
     */
    public function testAdditionalPropertiesRef() {
        $sch = $this->schema('additionalProperties');

        $valid = $sch->validate(['a' => '1', 'b' => 2.0]);
        $this->assertSame(['a' => 1, 'b' => 2], $valid);
    }

    /**
     * Cyclical references should result in an exception and not a critical error.
     *
     * @expectedException \Garden\Schema\RefNotFoundException
     * @expectedExceptionCode 508
     */
    public function testCyclicalRef() {
        $sch = $this->schema('cycle');

        $valid = $sch->validate(123);
    }

    /**
     * Tree-like schemas should be able to recursively reference themselves.
     */
    public function testRecursiveTree() {
        $sch = $this->schema('category');

        $valid = $sch->validate(['name' => 1, 'children' => [['name' => 11], ['name' => 12]]]);
        $this->assertSame(['name' => '1', 'children' => [['name' => '11'], ['name' => '12']]], $valid);
    }

    /**
     * References that cannot be found should throw an exception when validating.
     *
     * @expectedException \Garden\Schema\RefNotFoundException
     * @expectedExceptionCode 404
     */
    public function testRefNotFound() {
        $sch = $this->schema('nowhere');
        $valid = $sch->validate('foo');
    }

    /**
     * Exceptions from reference lookups should be re-thrown.
     *
     * @expectedException \Garden\Schema\RefNotFoundException
     * @expectedExceptionCode 400
     */
    public function testRefException() {
        $sch = $this->schema('local-ref');
        $valid = $sch->validate('foo');
    }

    /**
     * Filters work when added to the location of a reference.
     */
    public function testRefFilter() {
        $sch = $this->schema('ref')->addFilter('#/schemas/value', function ($v) {
            return $v * 10;
        });

        $valid = $sch->validate(10);
        $this->assertSame(100, $valid);
    }

    /**
     * Validators work when added to the location of a reference.
     *
     * @expectedException \Garden\Schema\ValidationException
     */
    public function testRefValidator() {
        $sch = $this->schema('ref')->addValidator('#/schemas/value', function ($v) {
            return $v < 10;
        });

        $valid = $sch->validate(11);
    }

    /**
     * Create a test schema at a given key.
     *
     * @param string $key The key of the test array.
     * @return Schema Returns a new schema.
     */
    protected function schema(string $key): Schema {
        $sch = new Schema($this->sch[$key]);
        $sch->setRefLookup($this->lookup);

        return $sch;
    }

    /**
     * By default references should not be found.
     *
     * @expectedException \Garden\Schema\RefNotFoundException
     */
    public function testDefaultRefNotFound() {
        $sch = new Schema(['$ref' => '#/foo']);

        $valid = $sch->validate(123);
    }
}
