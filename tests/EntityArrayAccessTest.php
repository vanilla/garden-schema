<?php

namespace Garden\Schema\Tests;

use Garden\Schema\Tests\Fixtures\ChildEntity;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Entity ArrayAccess interface implementation.
 */
class EntityArrayAccessTest extends TestCase {
    public function testArrayAccess(): void {
        $entity = ChildEntity::from(['id' => 'test-123']);

        // offsetExists
        $this->assertTrue(isset($entity['id']));
        $this->assertFalse(isset($entity['nonexistent']));

        // offsetGet
        $this->assertSame('test-123', $entity['id']);
        $this->assertNull($entity['nonexistent']);

        // offsetSet
        $entity['id'] = 'updated-456';
        $this->assertSame('updated-456', $entity['id']);
        $this->assertSame('updated-456', $entity->id);
    }

    public function testArrayAccessSetInvalidProperty(): void {
        $entity = ChildEntity::from(['id' => 'test']);

        $this->expectException(\InvalidArgumentException::class);
        $entity['invalid_property'] = 'value';
    }

    public function testArrayAccessUnsetThrows(): void {
        $entity = ChildEntity::from(['id' => 'test']);

        $this->expectException(\BadMethodCallException::class);
        unset($entity['id']);
    }

    public function testOffsetExistsWithNonExistentProperty(): void {
        $entity = ChildEntity::from(['id' => 'test']);

        // Should return false, not throw
        $this->assertFalse(isset($entity['nonExistentProperty']));
    }

    public function testOffsetGetWithNonExistentProperty(): void {
        $entity = ChildEntity::from(['id' => 'test']);

        // Should return null, not throw
        $this->assertNull($entity['nonExistentProperty']);
    }
}
