<?php

namespace Garden\Schema\Tests;

use ArrayObject;
use Garden\Schema\Tests\Fixtures\ArrayObjectEntity;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Entity properties typed as ArrayObject.
 */
class EntityArrayObjectTest extends TestCase {
    public function testArrayObjectPropertySchema(): void {
        $schema = ArrayObjectEntity::getSchema();
        $schemaArray = $schema->getSchemaArray();
        $properties = $schemaArray['properties'];

        $this->assertSame('object', $properties['data']['type']);
        $this->assertSame('object', $properties['optionalData']['type']);
        $this->assertTrue($properties['optionalData']['nullable']);
    }

    public function testArrayObjectPropertyFromArray(): void {
        $entity = ArrayObjectEntity::from([
            'name' => 'test',
            'data' => ['key1' => 'value1', 'key2' => 'value2'],
        ]);

        $this->assertSame('test', $entity->name);
        $this->assertInstanceOf(ArrayObject::class, $entity->data);
        $this->assertSame('value1', $entity->data['key1']);
        $this->assertSame('value2', $entity->data['key2']);
        $this->assertNull($entity->optionalData);
    }

    public function testArrayObjectPropertyToArray(): void {
        $entity = ArrayObjectEntity::from([
            'name' => 'test',
            'data' => ['nested' => ['a' => 1, 'b' => 2]],
            'optionalData' => ['optional' => true],
        ]);

        $array = $entity->toArray();

        $this->assertSame('test', $array['name']);
        // ArrayObject is preserved in toArray() for proper JSON serialization
        $this->assertInstanceOf(ArrayObject::class, $array['data']);
        $this->assertSame(['a' => 1, 'b' => 2], $array['data']['nested']);
        $this->assertInstanceOf(ArrayObject::class, $array['optionalData']);
        $this->assertSame(true, $array['optionalData']['optional']);
    }

    public function testArrayObjectPropertyRoundTrip(): void {
        $input = [
            'name' => 'roundtrip',
            'data' => ['foo' => 'bar', 'baz' => [1, 2, 3]],
        ];

        $entity = ArrayObjectEntity::from($input);
        $output = $entity->toArray();

        $this->assertSame($input['name'], $output['name']);
        // ArrayObject is preserved, so compare via getArrayCopy()
        $this->assertSame($input['data'], $output['data']->getArrayCopy());
    }

    public function testArrayObjectEmptySerializesToObject(): void {
        $entity = ArrayObjectEntity::from([
            'name' => 'empty-test',
            'data' => [],
        ]);

        $json = json_encode($entity);

        // Empty ArrayObject should serialize to {} not []
        $this->assertStringContainsString('"data":{}', $json);
    }

    public function testArrayObjectPropertyJsonSerialize(): void {
        $entity = ArrayObjectEntity::from([
            'name' => 'json-test',
            'data' => ['key' => 'value'],
        ]);

        $json = json_encode($entity);
        $decoded = json_decode($json, true);

        $this->assertSame('json-test', $decoded['name']);
        $this->assertSame(['key' => 'value'], $decoded['data']);
    }
}
