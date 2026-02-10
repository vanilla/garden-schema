<?php

namespace Garden\Schema\Tests;

use Garden\Schema\Tests\Fixtures\SchemaOrderEntity;
use PHPUnit\Framework\TestCase;

/**
 * Tests for SchemaOrder attribute property ordering.
 */
class SchemaOrderTest extends TestCase {
    /**
     * Test that schema properties are ordered correctly.
     *
     * Properties with SchemaOrder come first (sorted ascending by order value),
     * followed by properties without SchemaOrder in their original declaration order.
     */
    public function testSchemaPropertyOrder(): void {
        $schema = SchemaOrderEntity::getSchema();
        $schemaArray = $schema->getSchemaArray();
        $propertyNames = array_keys($schemaArray['properties']);

        // Expected: ordered properties first (id=1, name=2, title=3), then unordered (description, tags)
        $this->assertSame(['id', 'name', 'title', 'description', 'tags'], $propertyNames);
    }

    /**
     * Test that schema ordering does not affect validation or data roundtripping.
     */
    public function testSchemaOrderDoesNotAffectValidation(): void {
        $entity = SchemaOrderEntity::from([
            'id' => 1,
            'name' => 'Test',
            'title' => 'Test Title',
            'description' => 'A description',
            'tags' => ['a', 'b'],
        ]);

        $this->assertSame(1, $entity->id);
        $this->assertSame('Test', $entity->name);
        $this->assertSame('Test Title', $entity->title);
        $this->assertSame('A description', $entity->description);
        $this->assertSame(['a', 'b'], $entity->tags);
    }

    /**
     * Test that toArray() output preserves schema ordering.
     */
    public function testToArrayRespectsSchemaOrder(): void {
        $entity = SchemaOrderEntity::from([
            'id' => 1,
            'name' => 'Test',
            'title' => 'Test Title',
            'description' => 'A description',
            'tags' => ['a', 'b'],
        ]);

        $array = $entity->toArray();
        $keys = array_keys($array);

        $this->assertSame(['id', 'name', 'title', 'description', 'tags'], $keys);
    }
}
