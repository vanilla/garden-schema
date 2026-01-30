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
use Garden\Schema\Tests\Fixtures\ChildEntity;
use Garden\Schema\Tests\Fixtures\EntityWithInterfaceChild;
use Garden\Schema\Tests\Fixtures\StandaloneEntity;
use PHPUnit\Framework\TestCase;

/**
 * Tests for entities with properties typed as EntityInterface.
 */
class EntityInterfacePropertyTest extends TestCase {
    protected function setUp(): void {
        Entity::invalidateSchemaCache();
        StandaloneEntity::clearCache();
    }

    /**
     * Test that an entity with a property typed as EntityInterface can generate its schema.
     *
     * This was a bug where is_subclass_of($typeName, Entity::class) would fail for
     * properties typed as EntityInterface, causing an InvalidArgumentException.
     */
    public function testEntityWithInterfacePropertyGeneratesSchema(): void {
        // This should not throw an InvalidArgumentException
        $schema = EntityWithInterfaceChild::getSchema();

        $this->assertInstanceOf(Schema::class, $schema);
        $properties = $schema->getSchemaArray()['properties'];
        $this->assertArrayHasKey('name', $properties);
        $this->assertArrayHasKey('child', $properties);

        // The child property should be typed as object since EntityInterface can be any entity
        $this->assertSame('object', $properties['child']['type']);
        $this->assertTrue($properties['child']['nullable']);
    }

    /**
     * Test that an entity with EntityInterface property can be validated and hydrated.
     */
    public function testEntityWithInterfacePropertyFromValidData(): void {
        $entity = EntityWithInterfaceChild::from([
            'name' => 'Parent',
            'child' => null,
        ]);

        $this->assertInstanceOf(EntityWithInterfaceChild::class, $entity);
        $this->assertSame('Parent', $entity->name);
        $this->assertNull($entity->child);
    }

    /**
     * Test that an entity with EntityInterface property with child data.
     *
     * When a property is typed as EntityInterface (not a concrete Entity class),
     * the system cannot automatically hydrate array data into an entity instance.
     * The child will remain as the default value (null in this case).
     */
    public function testEntityWithInterfacePropertyWithChildData(): void {
        $entity = EntityWithInterfaceChild::from([
            'name' => 'Parent',
            'child' => ['id' => 'test-id'], // Array data can't auto-hydrate to EntityInterface
        ]);

        $this->assertInstanceOf(EntityWithInterfaceChild::class, $entity);
        $this->assertSame('Parent', $entity->name);
        // The child should remain null since we can't determine which entity type to hydrate to
        $this->assertNull($entity->child);
    }

    /**
     * Test that an EntityInterface-typed property can be assigned a concrete Entity instance.
     */
    public function testEntityWithInterfacePropertyAssignment(): void {
        $entity = new EntityWithInterfaceChild();
        $entity->name = 'Parent';
        
        // Assign a ChildEntity to the EntityInterface property
        $childEntity = ChildEntity::from(['id' => 'child-id']);
        $entity->child = $childEntity;

        $this->assertInstanceOf(ChildEntity::class, $entity->child);
        $this->assertSame('child-id', $entity->child->id);
    }

    /**
     * Test toArray with EntityInterface-typed property containing an entity.
     */
    public function testEntityWithInterfacePropertyToArray(): void {
        $entity = new EntityWithInterfaceChild();
        $entity->name = 'Parent';
        $entity->child = ChildEntity::from(['id' => 'child-id']);

        $array = $entity->toArray();

        $this->assertSame([
            'name' => 'Parent',
            'child' => ['id' => 'child-id'],
        ], $array);
    }

    /**
     * Test toArray with EntityInterface-typed property containing a StandaloneEntity.
     */
    public function testEntityWithInterfacePropertyToArrayWithStandaloneEntity(): void {
        $entity = new EntityWithInterfaceChild();
        $entity->name = 'Parent';
        $entity->child = StandaloneEntity::from([
            'id' => 1,
            'name' => 'Standalone Child',
            'description' => 'A standalone entity',
        ]);

        $array = $entity->toArray();

        $this->assertSame([
            'name' => 'Parent',
            'child' => [
                'id' => 1,
                'name' => 'Standalone Child',
                'description' => 'A standalone entity',
            ],
        ], $array);
    }
}
