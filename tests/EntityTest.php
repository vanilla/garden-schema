<?php

namespace Garden\Schema\Tests;

use Garden\Schema\Entity;
use Garden\Schema\Schema;
use Garden\Schema\Tests\Fixtures\AltNamesEntity;
use Garden\Schema\Tests\Fixtures\BasicEntity;
use Garden\Schema\Tests\Fixtures\ChildEntity;
use Garden\Schema\Tests\Fixtures\EntityWithChildrenArray;
use Garden\Schema\Tests\Fixtures\EntityWithExcludedProperty;
use Garden\Schema\Tests\Fixtures\IntEnum;
use Garden\Schema\Tests\Fixtures\IntEnumEntity;
use Garden\Schema\Tests\Fixtures\TestEnum;
use Garden\Schema\Tests\Fixtures\TreeNodeEntity;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Entity class and related attributes.
 */
class EntityTest extends TestCase {
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
        $expectedRequired = [
            'child',
            'count',
            'labels',
            'name',
            'names',
            'ratio',
            'status',
            'tags',
        ];
        sort($expectedRequired);
        $this->assertSame($expectedRequired, $required);
    }

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

    public function testPrivateAndProtectedFieldsExcluded(): void {
        $schema = BasicEntity::getSchema();
        $properties = $schema->getSchemaArray()['properties'];

        $this->assertArrayNotHasKey('secret', $properties, 'Private fields should not be in schema');
        $this->assertArrayNotHasKey('internal', $properties, 'Protected fields should not be in schema');
    }

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

        $this->expectException(\Garden\Schema\ValidationException::class);
        $schema->validate(['child' => ['wrong_field' => 'value']]);
    }

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

    public function testPropertyAltNamesUsesFirstAltName(): void {
        $entity = AltNamesEntity::from([
            'user_name' => 'John',
            'count' => 5,
        ]);

        $this->assertSame('John', $entity->name);
    }

    public function testPropertyAltNamesUsesSecondAltName(): void {
        $entity = AltNamesEntity::from([
            'userName' => 'Jane',
            'count' => 10,
        ]);

        $this->assertSame('Jane', $entity->name);
    }

    public function testPropertyAltNamesUsesThirdAltName(): void {
        $entity = AltNamesEntity::from([
            'uname' => 'Bob',
            'count' => 15,
        ]);

        $this->assertSame('Bob', $entity->name);
    }

    public function testPropertyAltNamesPrefersMainName(): void {
        $entity = AltNamesEntity::from([
            'name' => 'MainName',
            'user_name' => 'AltName',
            'count' => 20,
        ]);

        $this->assertSame('MainName', $entity->name);
    }

    public function testPropertyAltNamesMultipleProperties(): void {
        $entity = AltNamesEntity::from([
            'userName' => 'Alice',
            'e-mail' => 'alice@example.com',
            'count' => 25,
        ]);

        $this->assertSame('Alice', $entity->name);
        $this->assertSame('alice@example.com', $entity->email);
    }

    public function testPropertyAltNamesFirstMatchWins(): void {
        // When multiple alt names are present, the first defined alt name wins
        $entity = AltNamesEntity::from([
            'uname' => 'Third',
            'userName' => 'Second',
            'user_name' => 'First',
            'count' => 30,
        ]);

        // user_name is first in the attribute, so it should be used
        $this->assertSame('First', $entity->name);
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

    public function testExcludeFromSchemaNotInSchema(): void {
        $schema = EntityWithExcludedProperty::getSchema();
        $schemaArray = $schema->getSchemaArray();
        $properties = $schemaArray['properties'];

        // Excluded properties should not be in schema
        $this->assertArrayHasKey('name', $properties);
        $this->assertArrayHasKey('count', $properties);
        $this->assertArrayNotHasKey('computed', $properties);
        $this->assertArrayNotHasKey('cache', $properties);

        // Required should only include non-excluded properties
        $this->assertContains('name', $schemaArray['required']);
        $this->assertContains('count', $schemaArray['required']);
        $this->assertNotContains('computed', $schemaArray['required'] ?? []);
        $this->assertNotContains('cache', $schemaArray['required'] ?? []);
    }

    public function testExcludeFromSchemaNotPopulatedByFrom(): void {
        $entity = EntityWithExcludedProperty::from([
            'name' => 'test',
            'count' => 42,
            'computed' => 'should be ignored',
            'cache' => ['ignored' => true],
        ]);

        $this->assertSame('test', $entity->name);
        $this->assertSame(42, $entity->count);
        // Excluded properties keep their default values
        $this->assertSame('', $entity->computed);
        $this->assertNull($entity->cache);
    }

    public function testExcludeFromSchemaNotInToArray(): void {
        $entity = EntityWithExcludedProperty::from([
            'name' => 'test',
            'count' => 42,
        ]);

        // Manually set excluded properties
        $entity->computed = 'computed value';
        $entity->cache = ['cached' => true];

        $array = $entity->toArray();

        // Excluded properties should not be in array output
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('count', $array);
        $this->assertArrayNotHasKey('computed', $array);
        $this->assertArrayNotHasKey('cache', $array);
    }

    public function testExcludeFromSchemaCanBeSetDirectly(): void {
        $entity = EntityWithExcludedProperty::from([
            'name' => 'test',
            'count' => 1,
        ]);

        // Excluded properties can still be set directly
        $entity->computed = 'manually set';
        $entity->cache = ['data' => 'value'];

        $this->assertSame('manually set', $entity->computed);
        $this->assertSame(['data' => 'value'], $entity->cache);
    }

    public function testExcludeFromSchemaValidationIgnoresExtraFields(): void {
        // Validation should succeed even with extra data for excluded fields
        $entity = EntityWithExcludedProperty::from([
            'name' => 'test',
            'count' => 5,
            'computed' => 'extra data',
            'cache' => ['extra' => 'data'],
            'unknownField' => 'also ignored by default',
        ]);

        $this->assertSame('test', $entity->name);
        $this->assertSame(5, $entity->count);
    }

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

        $this->expectException(\Garden\Schema\ValidationException::class);
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
