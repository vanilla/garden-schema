<?php

namespace Garden\Schema\Tests;

use Garden\Schema\Tests\Fixtures\BasicEntity;
use Garden\Schema\Tests\Fixtures\TestEnum;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Entity schema generation from class properties.
 */
class EntitySchemaTest extends TestCase {
    public function testGetSchemaReflectsProperties(): void {
        $schema = BasicEntity::getSchema();
        $schemaArray = $schema->getSchemaArray();
        $properties = $schemaArray['properties'];

        $this->assertSame('object', $schemaArray['type']);
        $this->assertSame('string', $properties['name']['type']);
        $this->assertSame('integer', $properties['count']['type']);
        $this->assertSame('number', $properties['ratio']['type']);
        $this->assertSame('array', $properties['tags']['type']);
        $this->assertSame('array', $properties['labels']['type']);
        $this->assertSame('string', $properties['labels']['items']['type']);
        $this->assertSame(1, $properties['names']['minItems']);
        $this->assertTrue($properties['note']['nullable']);
        $this->assertSame('x', $properties['withDefault']['default']);
        $this->assertSame(TestEnum::class, $properties['status']['enumClassName']);
        $this->assertSame('object', $properties['child']['type']);
        $this->assertSame('string', $properties['child']['properties']['id']['type']);
        $this->assertTrue($properties['maybeChild']['nullable']);

        $required = $schemaArray['required'];
        sort($required);
        // raw is untyped so PHP gives it an implicit default of null, making it optional
        // withDefault has a default value but is still required (schema will apply the default)
        $expectedRequired = [
            'child',
            'count',
            'labels',
            'name',
            'names',
            'ratio',
            'status',
            'tags',
            'withDefault',
        ];
        sort($expectedRequired);
        $this->assertSame($expectedRequired, $required);
    }

    public function testPrivateAndProtectedFieldsExcluded(): void {
        $schema = BasicEntity::getSchema();
        $properties = $schema->getSchemaArray()['properties'];

        $this->assertArrayNotHasKey('secret', $properties, 'Private fields should not be in schema');
        $this->assertArrayNotHasKey('internal', $properties, 'Protected fields should not be in schema');
    }
}
