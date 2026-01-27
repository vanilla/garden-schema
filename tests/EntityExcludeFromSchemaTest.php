<?php

namespace Garden\Schema\Tests;

use Garden\Schema\Tests\Fixtures\EntityWithExcludedProperty;
use PHPUnit\Framework\TestCase;

/**
 * Tests for ExcludeFromSchema attribute on Entity properties.
 */
class EntityExcludeFromSchemaTest extends TestCase {
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
}
