<?php

namespace Garden\Schema\Tests;

use Garden\Schema\Tests\Fixtures\BasicEntity;
use Garden\Schema\Tests\Fixtures\ChildEntity;
use Garden\Schema\ValidationException;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Entity::validate() method.
 */
class EntityValidateTest extends TestCase {
    public function testValidateReturnsNewValidatedEntity(): void {
        $entity = ChildEntity::from(['id' => 'original']);

        // Modify directly (bypasses validation)
        $entity->id = 'modified';

        // Validate returns a new instance
        $validated = $entity->validate();

        $this->assertInstanceOf(ChildEntity::class, $validated);
        $this->assertNotSame($entity, $validated);
        $this->assertSame('modified', $validated->id);
    }

    public function testValidateThrowsOnInvalidState(): void {
        $entity = BasicEntity::from([
            'name' => 'test',
            'count' => 42,
            'ratio' => 1.5,
            'tags' => [],
            'labels' => ['a'],
            'names' => ['b'],
            'status' => 'one',
            'child' => ['id' => 'c1'],
        ]);

        // Set an invalid value directly - names has minItems: 1 constraint
        // This passes PHP type check (array) but fails schema validation
        $entity->names = [];

        $this->expectException(ValidationException::class);
        $entity->validate();
    }

    public function testValidateAfterValidModification(): void {
        $entity = BasicEntity::from([
            'name' => 'original',
            'count' => 1,
            'ratio' => 1.0,
            'tags' => [],
            'labels' => ['x'],
            'names' => ['y'],
            'status' => 'one',
            'child' => ['id' => 'c1'],
        ]);

        // Valid modifications
        $entity->name = 'updated';
        $entity->count = 100;

        // Should succeed and return new validated entity
        $validated = $entity->validate();

        $this->assertSame('updated', $validated->name);
        $this->assertSame(100, $validated->count);
    }

    public function testValidateWithNestedEntity(): void {
        $entity = BasicEntity::from([
            'name' => 'parent',
            'count' => 1,
            'ratio' => 1.0,
            'tags' => [],
            'labels' => ['x'],
            'names' => ['y'],
            'status' => 'two',
            'child' => ['id' => 'original-child'],
        ]);

        // Modify nested entity directly
        $entity->child->id = 'modified-child';

        $validated = $entity->validate();

        $this->assertSame('modified-child', $validated->child->id);
    }
}
