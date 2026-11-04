<?php
/**
 * @copyright 2009-2026 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema\Tests;

use Garden\Schema\Entity;
use Garden\Schema\EntitySchemaCache;
use Garden\Schema\Schema;
use Garden\Schema\SchemaVariant;
use PHPUnit\Framework\TestCase;

/**
 * Entities that override `getSchema()` only run the override when validating, not for each row they hydrate or serialize.
 */
class EntityStaticSchemaTest extends TestCase {
    protected function setUp(): void {
        EntitySchemaCache::invalidateAll();
        CountingEntity::$getSchemaCalls = 0;
    }

    public function testHydratingAndSerializingNeverCallsTheOverride(): void {
        $rows = [];
        for ($i = 0; $i < 50; $i++) {
            $rows[] = ['id' => $i, 'name' => "Row $i", 'child' => ['label' => "Child $i"]];
        }

        // Validation is where the override is meant to run. Once.
        $clean = CountingEntity::getSchema()->validate($rows[0]);
        $this->assertSame(1, CountingEntity::$getSchemaCalls);

        CountingEntity::$getSchemaCalls = 0;
        $entities = array_map(fn(array $row) => CountingEntity::fromValidated($row), $rows);
        $serialized = array_map(fn(CountingEntity $entity) => $entity->toArray(), $entities);

        $this->assertSame(0, CountingEntity::$getSchemaCalls);
        $this->assertSame("Child 7", $entities[7]->child->label);
        $this->assertSame(['label' => 'Child 7'], $serialized[7]['child']);
        $this->assertSame($rows[0], $serialized[0]);
        $this->assertSame($rows[0]['name'], $clean['name']);
    }

    public function testNestedSchemaBuildingSkipsTheNestedOverride(): void {
        EntitySchemaCache::invalidateAll();
        CountingChild::$getSchemaCalls = 0;

        CountingEntity::getSchema();

        $this->assertSame(0, CountingChild::$getSchemaCalls);
    }

    public function testPropertyMetadataIsRebuiltAfterInvalidation(): void {
        $entity = new CountingEntity();
        $entity['name'] = 'one';
        $this->assertTrue(isset($entity['name']));
        $this->assertFalse(isset($entity['id']), 'Uninitialized properties do not exist.');

        Entity::invalidateSchemaCache(CountingEntity::class);

        $this->assertTrue(isset($entity['name']));
        $this->assertSame('one', $entity['name']);
    }

    public function testOffsetSetStillRejectsUnknownProperties(): void {
        $entity = new CountingEntity();
        $this->expectException(\InvalidArgumentException::class);
        $entity['nope'] = 1;
    }
}

/**
 * Child entity that counts its `getSchema()` calls.
 */
class CountingChild extends Entity {
    public static int $getSchemaCalls = 0;

    public string $label;

    /**
     * @inheritdoc
     */
    public static function getSchema(?\BackedEnum $variant = null): Schema {
        self::$getSchemaCalls++;
        return parent::getSchema($variant);
    }
}

/**
 * Entity that counts its `getSchema()` calls.
 */
class CountingEntity extends Entity {
    public static int $getSchemaCalls = 0;

    public int $id;

    public string $name;

    public ?CountingChild $child;

    /**
     * @inheritdoc
     */
    public static function getSchema(?\BackedEnum $variant = null): Schema {
        self::$getSchemaCalls++;
        return parent::getSchema($variant);
    }
}
