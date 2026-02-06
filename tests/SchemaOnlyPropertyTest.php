<?php

namespace Garden\Schema\Tests;

use Garden\Schema\Tests\Fixtures\SchemaOnlyPropertyEntity;
use PHPUnit\Framework\TestCase;

/**
 * Tests for schema-only properties (private/protected with PropertySchema attribute).
 *
 * Private/protected properties with PropertySchema are included in getSchema() output
 * but NOT in encoding/decoding (fromValidated, toArray) since we can't set private
 * properties from a parent class.
 */
class SchemaOnlyPropertyTest extends TestCase {
    public function testPrivatePropertyWithPropertySchemaInSchema(): void {
        $schema = SchemaOnlyPropertyEntity::getSchema();
        $properties = $schema->getSchemaArray()['properties'];

        $this->assertArrayHasKey('schemaOnlyPrivate', $properties);
        $this->assertSame('string', $properties['schemaOnlyPrivate']['type']);
        $this->assertSame('A schema-only private field', $properties['schemaOnlyPrivate']['description']);
    }

    public function testProtectedPropertyWithPropertySchemaInSchema(): void {
        $schema = SchemaOnlyPropertyEntity::getSchema();
        $properties = $schema->getSchemaArray()['properties'];

        $this->assertArrayHasKey('schemaOnlyProtected', $properties);
        $this->assertSame('integer', $properties['schemaOnlyProtected']['type']);
        $this->assertSame(0, $properties['schemaOnlyProtected']['minimum']);
        $this->assertSame('A schema-only protected field', $properties['schemaOnlyProtected']['description']);
    }

    public function testPrivatePropertyWithoutPropertySchemaNotInSchema(): void {
        $schema = SchemaOnlyPropertyEntity::getSchema();
        $properties = $schema->getSchemaArray()['properties'];

        $this->assertArrayNotHasKey('secret', $properties, 'Private fields without PropertySchema should not be in schema');
    }

    public function testProtectedPropertyWithoutPropertySchemaNotInSchema(): void {
        $schema = SchemaOnlyPropertyEntity::getSchema();
        $properties = $schema->getSchemaArray()['properties'];

        $this->assertArrayNotHasKey('internal', $properties, 'Protected fields without PropertySchema should not be in schema');
    }

    public function testSchemaOnlyPropertiesNotInToArray(): void {
        $entity = SchemaOnlyPropertyEntity::from(['name' => 'test', 'count' => 5]);
        $array = $entity->toArray();

        $this->assertSame('test', $array['name']);
        $this->assertSame(5, $array['count']);
        $this->assertArrayNotHasKey('schemaOnlyPrivate', $array, 'Schema-only private properties should not be in toArray()');
        $this->assertArrayNotHasKey('schemaOnlyProtected', $array, 'Schema-only protected properties should not be in toArray()');
    }

    public function testSchemaOnlyPropertiesIgnoredInFromValidated(): void {
        // Even if the input includes values for schema-only properties, they should be ignored
        // since we can't set private/protected properties from the parent Entity class
        $entity = SchemaOnlyPropertyEntity::fromValidated([
            'name' => 'test',
            'count' => 5,
            'schemaOnlyPrivate' => 'attempted-value',
            'schemaOnlyProtected' => 999,
        ]);

        // The entity should have the public properties set
        $this->assertSame('test', $entity->name);
        $this->assertSame(5, $entity->count);

        // The array output should NOT include the schema-only properties
        $array = $entity->toArray();
        $this->assertArrayNotHasKey('schemaOnlyPrivate', $array);
        $this->assertArrayNotHasKey('schemaOnlyProtected', $array);
    }

    public function testPublicPropertiesStillWorkNormally(): void {
        $schema = SchemaOnlyPropertyEntity::getSchema();
        $properties = $schema->getSchemaArray()['properties'];

        $this->assertArrayHasKey('name', $properties);
        $this->assertArrayHasKey('count', $properties);
        $this->assertSame('string', $properties['name']['type']);
        $this->assertSame('integer', $properties['count']['type']);
    }

    public function testSchemaOnlyPropertiesAreNotRequired(): void {
        $schema = SchemaOnlyPropertyEntity::getSchema();
        $schemaArray = $schema->getSchemaArray();
        $required = $schemaArray['required'] ?? [];

        // Schema-only properties have defaults, so they shouldn't be required anyway,
        // but also they can't be set, so they should never be required
        $this->assertNotContains('schemaOnlyPrivate', $required);
        $this->assertNotContains('schemaOnlyProtected', $required);

        // Public properties without defaults should still be required
        $this->assertContains('name', $required);
        $this->assertContains('count', $required);
    }
}
