<?php

namespace Garden\Schema\Tests;

use Garden\Schema\Tests\Fixtures\BasicEntity;
use Garden\Schema\Tests\Fixtures\ChildEntity;
use Garden\Schema\Tests\Fixtures\TestEnum;
use Garden\Schema\ValidationException;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Entity::from() method.
 */
class EntityFromTest extends TestCase {
    public function testFromCastsEntities(): void {
        $entity = BasicEntity::from([
            'name' => 'alpha',
            'count' => 3,
            'ratio' => 1.5,
            'tags' => ['one'],
            'labels' => ['a', 'b'],
            'names' => ['c'],
            'raw' => ['freeform' => true],
            'status' => 'one',
            'child' => ['id' => 'child-1'],
            'maybeChild' => null,
        ]);

        $this->assertInstanceOf(BasicEntity::class, $entity);
        $this->assertSame('alpha', $entity->name);
        $this->assertSame('x', $entity->withDefault);
        $this->assertSame(TestEnum::One, $entity->status);
        $this->assertInstanceOf(ChildEntity::class, $entity->child);
        $this->assertSame('child-1', $entity->child->id);
        $this->assertNull($entity->maybeChild);
        $this->assertEquals(['freeform' => true], $entity->raw);
        $this->assertEquals(['a', 'b'], $entity->labels);
    }

    public function testFromAcceptsExistingEntity(): void {
        $original = ChildEntity::from(['id' => 'original-id']);
        $result = ChildEntity::from($original);

        $this->assertSame($original, $result);
    }

    public function testFromAcceptsExistingEntitySkipsValidation(): void {
        $entity = new ChildEntity();
        // Manually set a value that wouldn't pass validation (if it were validated)
        $entity->id = 'manually-set';

        $result = ChildEntity::from($entity);

        $this->assertSame($entity, $result);
        $this->assertSame('manually-set', $result->id);
    }

    public function testManuallySetAndValidateFails(): void {
        $entity = ChildEntity::from(['id' => 'my-id']);

        // I can set the value and it's invalid
        $entity->id = '';
        $this->expectException(ValidationException::class);
        $entity->validate();
    }
}
