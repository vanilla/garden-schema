<?php

namespace Garden\Schema\Tests;

use Garden\Schema\Tests\Fixtures\BasicEntity;
use Garden\Schema\Tests\Fixtures\ChildEntity;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Entity::toArray() and JSON serialization.
 */
class EntityToArrayTest extends TestCase {
    public function testToArrayRoundTrip(): void {
        $input = [
            'name' => 'alpha',
            'count' => 3,
            'ratio' => 1.5,
            'tags' => ['one', 'two'],
            'labels' => ['a', 'b'],
            'names' => ['c'],
            'raw' => ['freeform' => true],
            'status' => 'one',
            'child' => ['id' => 'child-1'],
            'maybeChild' => null,
        ];

        $entity = BasicEntity::from($input);
        $output = $entity->toArray();

        // Check round-trip produces equivalent values
        $this->assertSame('alpha', $output['name']);
        $this->assertSame(3, $output['count']);
        $this->assertSame(1.5, $output['ratio']);
        $this->assertSame(['one', 'two'], $output['tags']);
        $this->assertSame(['a', 'b'], $output['labels']);
        $this->assertSame(['c'], $output['names']);
        $this->assertSame(['freeform' => true], $output['raw']);
        // Enum is converted to its backing value
        $this->assertSame('one', $output['status']);
        // Nested entity is converted to array
        $this->assertSame(['id' => 'child-1'], $output['child']);
        $this->assertNull($output['maybeChild']);
        // Default value is included
        $this->assertSame('x', $output['withDefault']);

        // Can create new entity from output
        $entity2 = BasicEntity::from($output);
        $this->assertSame($output, $entity2->toArray());
    }

    public function testToArrayWithNestedEntities(): void {
        $child = ChildEntity::from(['id' => 'nested-child']);
        $entity = BasicEntity::from([
            'name' => 'parent',
            'count' => 1,
            'ratio' => 2.0,
            'tags' => [],
            'labels' => ['x'],
            'names' => ['y'],
            'status' => 'two',
            'child' => $child, // Pass entity instance directly
        ]);

        $array = $entity->toArray();
        $this->assertSame(['id' => 'nested-child'], $array['child']);
    }

    public function testJsonSerialize(): void {
        $entity = BasicEntity::from([
            'name' => 'json-test',
            'count' => 42,
            'ratio' => 3.14,
            'tags' => ['tag1'],
            'labels' => ['label1'],
            'names' => ['name1'],
            'status' => 'three',
            'child' => ['id' => 'json-child'],
        ]);

        $json = json_encode($entity);
        $decoded = json_decode($json, true);

        $this->assertSame('json-test', $decoded['name']);
        $this->assertSame(42, $decoded['count']);
        $this->assertSame('three', $decoded['status']);
        $this->assertSame(['id' => 'json-child'], $decoded['child']);
    }
}
