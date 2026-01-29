<?php

namespace Garden\Schema\Tests;

use Garden\Schema\Entity;
use Garden\Schema\Schema;
use Garden\Schema\Tests\Fixtures\BasicEntity;
use Garden\Schema\Tests\Fixtures\ChildEntity;
use Garden\Schema\Tests\Fixtures\EntityWithChildrenArray;
use Garden\Schema\Tests\Fixtures\IntEnum;
use Garden\Schema\Tests\Fixtures\IntEnumEntity;
use Garden\Schema\Tests\Fixtures\TreeNodeEntity;
use Garden\Schema\ValidationException;
use PHPUnit\Framework\TestCase;

/**
 * Core tests for the Entity class.
 *
 * @see EntitySchemaTest for schema generation tests
 * @see EntityFromTest for Entity::from() tests
 * @see EntityToArrayTest for Entity::toArray() and JSON serialization tests
 * @see EntityArrayAccessTest for ArrayAccess interface tests
 * @see EntityPropertyAltNamesTest for PropertyAltNames attribute tests
 * @see EntityExcludeFromSchemaTest for ExcludeFromSchema attribute tests
 * @see EntityValidateTest for Entity::validate() tests
 * @see EntityArrayObjectTest for ArrayObject property tests
 * @see EntityDateTimeTest for DateTimeImmutable property tests
 */
class EntityTest extends TestCase {
    public function testEntityClassNameShorthand(): void {
        $schema = Schema::parse([
            'child' => ChildEntity::class,
        ]);

        $result = $schema->validate(['child' => ['id' => 'test-id']]);

        $this->assertInstanceOf(ChildEntity::class, $result['child']);
        $this->assertSame('test-id', $result['child']->id);
    }

    public function testEntityClassNameLongForm(): void {
        $schema = Schema::parse([
            'child' => ['entityClassName' => ChildEntity::class],
        ]);

        $result = $schema->validate(['child' => ['id' => 'test-id']]);

        $this->assertInstanceOf(ChildEntity::class, $result['child']);
        $this->assertSame('test-id', $result['child']->id);
    }

    public function testEntityClassNameInArray(): void {
        $schema = Schema::parse([
            'children:a' => ChildEntity::class,
        ]);

        $result = $schema->validate([
            'children' => [
                ['id' => 'child-1'],
                ['id' => 'child-2'],
            ],
        ]);

        $this->assertCount(2, $result['children']);
        $this->assertInstanceOf(ChildEntity::class, $result['children'][0]);
        $this->assertInstanceOf(ChildEntity::class, $result['children'][1]);
        $this->assertSame('child-1', $result['children'][0]->id);
        $this->assertSame('child-2', $result['children'][1]->id);
    }

    public function testEntityClassNameValidationError(): void {
        $schema = Schema::parse([
            'child' => ChildEntity::class,
        ]);

        $this->expectException(ValidationException::class);
        $schema->validate(['child' => ['wrong_field' => 'value']]);
    }

    public function testIntegerBackedEnum(): void {
        $schema = IntEnumEntity::getSchema();
        $schemaArray = $schema->getSchemaArray();

        $this->assertSame('integer', $schemaArray['properties']['priority']['type']);
        $this->assertSame(IntEnum::class, $schemaArray['properties']['priority']['enumClassName']);

        $entity = IntEnumEntity::from([
            'name' => 'task',
            'priority' => 2,
        ]);

        $this->assertSame(IntEnum::Two, $entity->priority);
        $this->assertSame(2, $entity->toArray()['priority']);
    }

    public function testIntegerBackedEnumWithStringInput(): void {
        // Regression test: string values should be coerced to int for integer-backed enums
        // This commonly happens with JSON/form data where numbers arrive as strings
        $entity = IntEnumEntity::from([
            'name' => 'task',
            'priority' => '2', // String input
        ]);

        $this->assertSame(IntEnum::Two, $entity->priority);
        $this->assertSame(2, $entity->toArray()['priority']);
    }

    public function testSelfReferencingEntity(): void {
        $entity = TreeNodeEntity::from([
            'value' => 'root',
            'child' => [
                'value' => 'child1',
                'child' => [
                    'value' => 'child2',
                    'child' => null,
                ],
            ],
        ]);

        $this->assertSame('root', $entity->value);
        $this->assertInstanceOf(TreeNodeEntity::class, $entity->child);
        $this->assertSame('child1', $entity->child->value);
        $this->assertInstanceOf(TreeNodeEntity::class, $entity->child->child);
        $this->assertSame('child2', $entity->child->child->value);
        $this->assertNull($entity->child->child->child);
    }

    public function testSelfReferencingEntityToArray(): void {
        $entity = TreeNodeEntity::from([
            'value' => 'root',
            'child' => [
                'value' => 'leaf',
            ],
        ]);

        $array = $entity->toArray();

        $this->assertSame([
            'value' => 'root',
            'child' => [
                'value' => 'leaf',
                'child' => null,
            ],
        ], $array);
    }

    public function testArrayOfNestedEntitiesViaPropertySchema(): void {
        $entity = EntityWithChildrenArray::from([
            'name' => 'parent',
            'children' => [
                ['id' => 'child-1'],
                ['id' => 'child-2'],
            ],
        ]);

        $this->assertSame('parent', $entity->name);
        $this->assertCount(2, $entity->children);
        $this->assertInstanceOf(ChildEntity::class, $entity->children[0]);
        $this->assertInstanceOf(ChildEntity::class, $entity->children[1]);
        $this->assertSame('child-1', $entity->children[0]->id);
        $this->assertSame('child-2', $entity->children[1]->id);
    }

    public function testArrayOfNestedEntitiesToArray(): void {
        $entity = EntityWithChildrenArray::from([
            'name' => 'parent',
            'children' => [
                ['id' => 'child-1'],
                ['id' => 'child-2'],
            ],
        ]);

        $array = $entity->toArray();

        $this->assertSame([
            'name' => 'parent',
            'children' => [
                ['id' => 'child-1'],
                ['id' => 'child-2'],
            ],
        ], $array);
    }

    public function testNullablePropertyWithoutDefaultGetsInitialized(): void {
        // BasicEntity has `public ?string $note;` which is nullable without a default
        $entity = BasicEntity::from([
            'name' => 'test',
            'count' => 1,
            'ratio' => 1.0,
            'tags' => [],
            'labels' => ['x'],
            'names' => ['y'],
            'status' => 'one',
            'child' => ['id' => 'c1'],
            // note is NOT provided
        ]);

        // Property should be initialized to null, not uninitialized
        $this->assertNull($entity->note);
        $this->assertTrue(isset($entity['note']) || $entity['note'] === null);

        // toArray should include it
        $array = $entity->toArray();
        $this->assertArrayHasKey('note', $array);
        $this->assertNull($array['note']);
    }

    public function testInvalidateSchemaCacheForSpecificClass(): void {
        // Get schema to cache it
        $schema1 = ChildEntity::getSchema();

        // Invalidate cache for this class
        Entity::invalidateSchemaCache(ChildEntity::class);

        // Getting schema again should create a new instance
        $schema2 = ChildEntity::getSchema();

        // They should be equal but not the same instance
        $this->assertEquals($schema1->getSchemaArray(), $schema2->getSchemaArray());
        $this->assertNotSame($schema1, $schema2);
    }

    public function testInvalidateSchemaCacheAll(): void {
        // Get schemas to cache them
        $childSchema1 = ChildEntity::getSchema();
        $treeSchema1 = TreeNodeEntity::getSchema();

        // Invalidate all caches
        Entity::invalidateSchemaCache();

        // Getting schemas again should create new instances
        $childSchema2 = ChildEntity::getSchema();
        $treeSchema2 = TreeNodeEntity::getSchema();

        $this->assertNotSame($childSchema1, $childSchema2);
        $this->assertNotSame($treeSchema1, $treeSchema2);
    }
}
