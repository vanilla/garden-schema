<?php

namespace Garden\Schema\Tests;

use DateTimeImmutable;
use DateTimeInterface;
use Garden\Schema\Tests\Fixtures\DateTimeEntity;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Entity properties typed as DateTimeImmutable.
 */
class EntityDateTimeTest extends TestCase {
    public function testDateTimeImmutablePropertySchema(): void {
        $schema = DateTimeEntity::getSchema();
        $schemaArray = $schema->getSchemaArray();
        $properties = $schemaArray['properties'];

        $this->assertSame('string', $properties['createdAt']['type']);
        $this->assertSame('date-time', $properties['createdAt']['format']);
        $this->assertSame('string', $properties['updatedAt']['type']);
        $this->assertSame('date-time', $properties['updatedAt']['format']);
        $this->assertTrue($properties['updatedAt']['nullable']);
    }

    public function testDateTimeImmutablePropertyFromString(): void {
        $entity = DateTimeEntity::from([
            'name' => 'test',
            'createdAt' => '2024-01-15T10:30:00+00:00',
        ]);

        $this->assertSame('test', $entity->name);
        $this->assertInstanceOf(DateTimeImmutable::class, $entity->createdAt);
        $this->assertSame('2024-01-15', $entity->createdAt->format('Y-m-d'));
        $this->assertSame('10:30:00', $entity->createdAt->format('H:i:s'));
        $this->assertNull($entity->updatedAt);
    }

    public function testDateTimeImmutablePropertyToArray(): void {
        $entity = DateTimeEntity::from([
            'name' => 'test',
            'createdAt' => '2024-06-20T14:45:30+00:00',
            'updatedAt' => '2024-06-21T09:00:00+00:00',
        ]);

        $array = $entity->toArray();

        $this->assertSame('test', $array['name']);
        $this->assertIsString($array['createdAt']);
        $this->assertStringContainsString('2024-06-20', $array['createdAt']);
        $this->assertIsString($array['updatedAt']);
        $this->assertStringContainsString('2024-06-21', $array['updatedAt']);
    }

    public function testDateTimeImmutablePropertyJsonSerialize(): void {
        $entity = DateTimeEntity::from([
            'name' => 'json-test',
            'createdAt' => '2024-03-10T08:15:00+00:00',
        ]);

        $json = json_encode($entity);
        $decoded = json_decode($json, true);

        $this->assertSame('json-test', $decoded['name']);
        $this->assertStringContainsString('2024-03-10', $decoded['createdAt']);
        $this->assertStringContainsString('08:15:00', $decoded['createdAt']);
    }

    public function testDateTimeImmutablePropertyRoundTrip(): void {
        $input = [
            'name' => 'roundtrip',
            'createdAt' => '2024-12-25T12:00:00+00:00',
        ];

        $entity = DateTimeEntity::from($input);
        $output = $entity->toArray();

        // Round-trip should preserve the date-time
        $entity2 = DateTimeEntity::from($output);
        $this->assertSame(
            $entity->createdAt->format(DateTimeInterface::RFC3339),
            $entity2->createdAt->format(DateTimeInterface::RFC3339)
        );
    }

    public function testDateTimeImmutableSerializesWithoutMilliseconds(): void {
        $entity = DateTimeEntity::from([
            'name' => 'no-ms',
            'createdAt' => '2024-06-15T14:00:00+00:00',
        ]);

        $array = $entity->toArray();

        // Without milliseconds, should use RFC3339 format
        $this->assertSame('2024-06-15T14:00:00+00:00', $array['createdAt']);
    }

    public function testDateTimeImmutableSerializesWithMilliseconds(): void {
        // Create a DateTimeImmutable with milliseconds
        $dt = new DateTimeImmutable('2024-06-15T14:00:00.123456+00:00');

        $entity = new DateTimeEntity();
        $entity->name = 'with-ms';
        $entity->createdAt = $dt;

        $array = $entity->toArray();

        // With milliseconds, should use RFC3339_EXTENDED format
        $this->assertSame('2024-06-15T14:00:00.123+00:00', $array['createdAt']);
    }
}
