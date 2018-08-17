<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema\Tests;

use Garden\Schema\Schema;

/**
 * Tests for OpenAPI's readOnly and writeOnly community.
 */
class InputOutputTest extends AbstractSchemaTest {
    /**
     * @var Schema
     */
    private $schema;

    /**
     * @var array
     */
    private $data;

    /**
     * Create some test data for each test.
     */
    public function setUp() {
        parent::setUp();

        $this->schema = new Schema([
            'type' => 'object',
            'properties' => [
                'r' => [
                    'type' => 'string',
                    'readOnly' => true,
                ],
                'w' => [
                    'type' => 'string',
                    'writeOnly' => true,
                ],
                'rw' => [
                    'type' => 'string'
                ]
            ],
            'additionalProperties' => true,
        ]);

        $this->data = [
            'r' => 'r',
            'w' => 'w',
            'rw' => 'rw',
        ];
    }

    /**
     * Requests should strip readOnly properties.
     */
    public function testReadOnlyRequestStrip() {
        $valid = $this->schema->validate($this->data, ['request' => true]);

        $this->assertEquals(['w' => 'w', 'rw' => 'rw'], $valid);
    }

    /**
     * Responses should strip writeOnly properties.
     */
    public function testWriteOnlyResponseStrip() {
        $valid = $this->schema->validate($this->data, ['response' => true]);
        $this->assertEquals(['r' => 'r', 'rw' => 'rw'], $valid);
    }

    /**
     * Requests should not treat readOnly properties as additional properties.
     */
    public function testReadOnlyWithAdditionalProperties() {
        $valid = $this->schema->validate($this->data + ['a' => 'a'], ['request' => true]);
        $this->assertEquals(['w' => 'w', 'rw' => 'rw', 'a' => 'a'], $valid);
    }

    /**
     * Responses should not treat writeOnly properties as additional properties.
     */
    public function testWriteOnlyWithAdditionalProperties() {
        $valid = $this->schema->validate($this->data + ['a' => 'a'], ['response' => true]);
        $this->assertEquals(['r' => 'r', 'rw' => 'rw', 'a' => 'a'], $valid);
    }
}
