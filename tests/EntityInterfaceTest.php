<?php
/**
 * @author Adam Charron <adam@vanillaforums.com>
 * @copyright 2009-2024 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema\Tests;

use Garden\Schema\Entity;
use Garden\Schema\EntityInterface;
use Garden\Schema\Schema;
use Garden\Schema\SchemaVariant;
use Garden\Schema\Tests\Fixtures\BasicEntity;
use Garden\Schema\Tests\Fixtures\ChildEntity;
use Garden\Schema\Tests\Fixtures\StandaloneEntity;
use Garden\Schema\ValidationException;
use PHPUnit\Framework\TestCase;

/**
 * Tests for EntityInterface.
 */
class EntityInterfaceTest extends TestCase {
    protected function setUp(): void {
        Entity::invalidateSchemaCache();
        StandaloneEntity::clearCache();
    }

    //
    // Interface Implementation Tests
    //

    public function testEntityImplementsInterface(): void {
        $this->assertTrue(
            is_subclass_of(ChildEntity::class, EntityInterface::class),
            'Entity subclasses should implement EntityInterface'
        );
    }

    public function testStandaloneEntityImplementsInterface(): void {
        $this->assertTrue(
            is_subclass_of(StandaloneEntity::class, EntityInterface::class),
            'StandaloneEntity should implement EntityInterface'
        );
    }

    public function testStandaloneEntityExtendsExistingClass(): void {
        $entity = new StandaloneEntity();
        $this->assertSame('from base class', $entity->baseClassMethod());
    }

    //
    // StandaloneEntity Tests - Proving interface works without extending Entity
    //

    public function testStandaloneEntityGetSchema(): void {
        $schema = StandaloneEntity::getSchema();

        $this->assertInstanceOf(Schema::class, $schema);
        $properties = $schema->getSchemaArray()['properties'];
        $this->assertArrayHasKey('id', $properties);
        $this->assertArrayHasKey('name', $properties);
        $this->assertArrayHasKey('description', $properties);
    }

    public function testStandaloneEntityGetSchemaWithVariant(): void {
        $fullSchema = StandaloneEntity::getSchema(SchemaVariant::Full);
        $fragmentSchema = StandaloneEntity::getSchema(SchemaVariant::Fragment);

        $fullProps = $fullSchema->getSchemaArray()['properties'];
        $fragmentProps = $fragmentSchema->getSchemaArray()['properties'];

        $this->assertArrayHasKey('description', $fullProps);
        $this->assertArrayNotHasKey('description', $fragmentProps);
    }

    public function testStandaloneEntityFrom(): void {
        $entity = StandaloneEntity::from([
            'id' => 1,
            'name' => 'Test',
            'description' => 'A description',
        ]);

        $this->assertInstanceOf(StandaloneEntity::class, $entity);
        $this->assertSame(1, $entity->id);
        $this->assertSame('Test', $entity->name);
        $this->assertSame('A description', $entity->description);
    }

    public function testStandaloneEntityFromWithVariant(): void {
        // Fragment variant doesn't require description
        $entity = StandaloneEntity::from([
            'id' => 1,
            'name' => 'Test',
        ], SchemaVariant::Fragment);

        $this->assertInstanceOf(StandaloneEntity::class, $entity);
        $this->assertSame(1, $entity->id);
        $this->assertSame('Test', $entity->name);
    }

    public function testStandaloneEntityFromValidationFails(): void {
        $this->expectException(ValidationException::class);
        StandaloneEntity::from([
            'id' => 'not-an-int', // Should fail validation
            'name' => 'Test',
        ]);
    }

    public function testStandaloneEntityFromValidated(): void {
        $entity = StandaloneEntity::fromValidated([
            'id' => 2,
            'name' => 'Validated',
            'description' => 'Pre-validated data',
        ]);

        $this->assertInstanceOf(StandaloneEntity::class, $entity);
        $this->assertSame(2, $entity->id);
        $this->assertSame('Pre-validated data', $entity->description);
    }

    public function testStandaloneEntityToArray(): void {
        $entity = StandaloneEntity::from([
            'id' => 1,
            'name' => 'Test',
            'description' => 'Desc',
        ]);

        $array = $entity->toArray();

        $this->assertSame([
            'id' => 1,
            'name' => 'Test',
            'description' => 'Desc',
        ], $array);
    }

    public function testStandaloneEntityToArrayWithVariant(): void {
        $entity = StandaloneEntity::from([
            'id' => 1,
            'name' => 'Test',
            'description' => 'Desc',
        ]);

        $array = $entity->toArray(SchemaVariant::Fragment);

        $this->assertSame([
            'id' => 1,
            'name' => 'Test',
        ], $array);
        $this->assertArrayNotHasKey('description', $array);
    }

    public function testStandaloneEntitySerializationVariant(): void {
        $entity = StandaloneEntity::from([
            'id' => 1,
            'name' => 'Test',
            'description' => 'Desc',
        ]);

        $this->assertNull($entity->getSerializationVariant());

        $entity->setSerializationVariant(SchemaVariant::Fragment);
        $this->assertSame(SchemaVariant::Fragment, $entity->getSerializationVariant());

        // toArray() should use the serialization variant
        $array = $entity->toArray();
        $this->assertArrayNotHasKey('description', $array);
    }

    public function testStandaloneEntityValidate(): void {
        $entity = new StandaloneEntity();
        $entity->id = 5;
        $entity->name = 'Manual';
        $entity->description = 'Manually set';

        $validated = $entity->validate();

        $this->assertInstanceOf(StandaloneEntity::class, $validated);
        $this->assertSame(5, $validated->id);
        $this->assertSame('Manual', $validated->name);
    }

    //
    // Type Checking Tests - EntityInterface as type hint
    //

    public function testInstanceofEntityInterface(): void {
        $childEntity = ChildEntity::from(['id' => 'test']);
        $standaloneEntity = StandaloneEntity::from(['id' => 1, 'name' => 'Test']);

        $this->assertInstanceOf(EntityInterface::class, $childEntity);
        $this->assertInstanceOf(EntityInterface::class, $standaloneEntity);
    }

    public function testFunctionAcceptingEntityInterface(): void {
        $childEntity = ChildEntity::from(['id' => 'test']);
        $standaloneEntity = StandaloneEntity::from(['id' => 1, 'name' => 'Test']);

        // Both should work with a function that accepts EntityInterface
        $this->assertSame(['id' => 'test'], $this->serializeEntity($childEntity));
        $this->assertSame(['id' => 1, 'name' => 'Test', 'description' => ''], $this->serializeEntity($standaloneEntity));
    }

    /**
     * Helper function that accepts any EntityInterface.
     */
    private function serializeEntity(EntityInterface $entity): array {
        return $entity->toArray();
    }

    //
    // is_subclass_of Tests
    //

    public function testIsSubclassOfEntityInterface(): void {
        $this->assertTrue(is_subclass_of(ChildEntity::class, EntityInterface::class));
        $this->assertTrue(is_subclass_of(BasicEntity::class, EntityInterface::class));
        $this->assertTrue(is_subclass_of(StandaloneEntity::class, EntityInterface::class));
    }
}
