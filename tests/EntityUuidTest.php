<?php

namespace Garden\Schema\Tests;

use Garden\Schema\Schema;
use Garden\Schema\Tests\Fixtures\UuidEntity;
use Garden\Schema\ValidationException;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * Tests for Entity properties typed as UuidInterface.
 */
class EntityUuidTest extends TestCase {
    public function testUuidPropertySchema(): void {
        $schema = UuidEntity::getSchema();
        $schemaArray = $schema->getSchemaArray();
        $properties = $schemaArray['properties'];

        $this->assertSame('string', $properties['id']['type']);
        $this->assertSame('uuid', $properties['id']['format']);
        $this->assertSame('string', $properties['parentId']['type']);
        $this->assertSame('uuid', $properties['parentId']['format']);
        $this->assertTrue($properties['parentId']['nullable']);
    }

    public function testUuidPropertyFromString(): void {
        $uuidString = '550e8400-e29b-41d4-a716-446655440000';
        $entity = UuidEntity::from([
            'name' => 'test',
            'id' => $uuidString,
        ]);

        $this->assertSame('test', $entity->name);
        $this->assertInstanceOf(UuidInterface::class, $entity->id);
        $this->assertSame($uuidString, $entity->id->toString());
        $this->assertNull($entity->parentId);
    }

    public function testUuidPropertyFromBytes(): void {
        $uuid = Uuid::uuid4();
        $bytes = $uuid->getBytes();

        $entity = UuidEntity::from([
            'name' => 'bytes-test',
            'id' => $bytes,
        ]);

        $this->assertInstanceOf(UuidInterface::class, $entity->id);
        $this->assertSame($uuid->toString(), $entity->id->toString());
    }

    public function testUuidPropertyToArray(): void {
        $uuidString = '550e8400-e29b-41d4-a716-446655440000';
        $parentUuidString = '660e8400-e29b-41d4-a716-446655440001';

        $entity = UuidEntity::from([
            'name' => 'test',
            'id' => $uuidString,
            'parentId' => $parentUuidString,
        ]);

        $array = $entity->toArray();

        $this->assertSame('test', $array['name']);
        $this->assertSame($uuidString, $array['id']);
        $this->assertSame($parentUuidString, $array['parentId']);
    }

    public function testUuidPropertyJsonSerialize(): void {
        $uuidString = '550e8400-e29b-41d4-a716-446655440000';
        $entity = UuidEntity::from([
            'name' => 'json-test',
            'id' => $uuidString,
        ]);

        $json = json_encode($entity);
        $decoded = json_decode($json, true);

        $this->assertSame('json-test', $decoded['name']);
        $this->assertSame($uuidString, $decoded['id']);
    }

    public function testUuidPropertyRoundTrip(): void {
        $input = [
            'name' => 'roundtrip',
            'id' => '550e8400-e29b-41d4-a716-446655440000',
        ];

        $entity = UuidEntity::from($input);
        $output = $entity->toArray();

        // Round-trip should preserve the UUID
        $entity2 = UuidEntity::from($output);
        $this->assertSame($entity->id->toString(), $entity2->id->toString());
    }

    public function testUuidPropertyWithUuidObject(): void {
        $uuid = Uuid::uuid4();

        $entity = new UuidEntity();
        $entity->name = 'object-test';
        $entity->id = $uuid;

        $array = $entity->toArray();
        $this->assertSame($uuid->toString(), $array['id']);
    }

    public function testUuidNullableProperty(): void {
        $entity = UuidEntity::from([
            'name' => 'nullable-test',
            'id' => '550e8400-e29b-41d4-a716-446655440000',
            'parentId' => null,
        ]);

        $this->assertNull($entity->parentId);

        $array = $entity->toArray();
        $this->assertNull($array['parentId']);
    }

    public function testInvalidUuidThrowsValidationException(): void {
        $this->expectException(ValidationException::class);

        UuidEntity::from([
            'name' => 'invalid-test',
            'id' => 'not-a-valid-uuid',
        ]);
    }

    public function testSchemaValidatesUuidFormat(): void {
        $schema = Schema::parse([
            'id:s' => ['format' => 'uuid'],
        ]);

        $valid = $schema->validate(['id' => '550e8400-e29b-41d4-a716-446655440000']);
        $this->assertInstanceOf(UuidInterface::class, $valid['id']);
    }

    public function testSchemaValidatesUuidFromBytes(): void {
        $schema = Schema::parse([
            'id:s' => ['format' => 'uuid'],
        ]);

        $uuid = Uuid::uuid4();
        $valid = $schema->validate(['id' => $uuid->getBytes()]);
        $this->assertInstanceOf(UuidInterface::class, $valid['id']);
        $this->assertSame($uuid->toString(), $valid['id']->toString());
    }

    public function testSchemaRejectsInvalidUuid(): void {
        $schema = Schema::parse([
            'id:s' => ['format' => 'uuid'],
        ]);

        $this->expectException(ValidationException::class);
        $schema->validate(['id' => 'invalid-uuid']);
    }

    public function testUuidV1(): void {
        $uuid = Uuid::uuid1();
        $entity = UuidEntity::from([
            'name' => 'v1-test',
            'id' => $uuid->toString(),
        ]);

        $this->assertSame($uuid->toString(), $entity->id->toString());
    }

    public function testUuidV4(): void {
        $uuid = Uuid::uuid4();
        $entity = UuidEntity::from([
            'name' => 'v4-test',
            'id' => $uuid->toString(),
        ]);

        $this->assertSame($uuid->toString(), $entity->id->toString());
    }

    public function testUuidPassThroughWhenAlreadyUuid(): void {
        $uuid = Uuid::uuid4();

        // Create schema and validate with UUID object directly
        $schema = Schema::parse([
            'id:s' => ['format' => 'uuid'],
        ]);

        $valid = $schema->validate(['id' => $uuid]);
        $this->assertSame($uuid, $valid['id']);
    }
}
