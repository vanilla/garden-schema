<?php

namespace Garden\Schema\Tests;

use Garden\Schema\Tests\Fixtures\DefaultMetadataEntity;
use Garden\Schema\Tests\Fixtures\EntityWithDefaultChild;
use PHPUnit\Framework\TestCase;

/**
 * Tests for EntityDefaultInterface functionality.
 */
class EntityDefaultTest extends TestCase {

    public function setUp(): void {
        DefaultMetadataEntity::invalidateSchemaCache();
        EntityWithDefaultChild::invalidateSchemaCache();
    }

    /**
     * Test that the default() method returns the expected instance.
     */
    public function testDefaultMethodReturnsInstance(): void {
        $default = DefaultMetadataEntity::default();

        $this->assertInstanceOf(DefaultMetadataEntity::class, $default);
        $this->assertSame('1.0', $default->version);
        $this->assertTrue($default->draft);
    }

    /**
     * Test that schema includes default value for entities implementing EntityDefaultInterface.
     */
    public function testSchemaIncludesDefault(): void {
        $schema = EntityWithDefaultChild::getSchema();
        $schemaArray = $schema->getSchemaArray();

        $this->assertArrayHasKey('properties', $schemaArray);
        $this->assertArrayHasKey('metadata', $schemaArray['properties']);

        $metadataSchema = $schemaArray['properties']['metadata'];
        $this->assertArrayHasKey('default', $metadataSchema);
        $this->assertEquals(['version' => '1.0', 'draft' => true], $metadataSchema['default']);
    }

    /**
     * Test that nullable properties with EntityDefaultInterface also get the default in schema.
     */
    public function testSchemaIncludesDefaultForNullableProperty(): void {
        $schema = EntityWithDefaultChild::getSchema();
        $schemaArray = $schema->getSchemaArray();

        $optionalMetadataSchema = $schemaArray['properties']['optionalMetadata'];
        $this->assertArrayHasKey('default', $optionalMetadataSchema);
        $this->assertEquals(['version' => '1.0', 'draft' => true], $optionalMetadataSchema['default']);
    }

    /**
     * Test that fromValidated applies the default when property is not provided.
     */
    public function testFromValidatedAppliesDefault(): void {
        $entity = EntityWithDefaultChild::fromValidated([
            'title' => 'Test Article',
        ]);

        $this->assertSame('Test Article', $entity->title);
        $this->assertInstanceOf(DefaultMetadataEntity::class, $entity->metadata);
        $this->assertSame('1.0', $entity->metadata->version);
        $this->assertTrue($entity->metadata->draft);
    }

    /**
     * Test that from() applies the default when property is not provided.
     */
    public function testFromAppliesDefault(): void {
        $entity = EntityWithDefaultChild::from([
            'title' => 'Test Article',
        ]);

        $this->assertSame('Test Article', $entity->title);
        $this->assertInstanceOf(DefaultMetadataEntity::class, $entity->metadata);
        $this->assertSame('1.0', $entity->metadata->version);
        $this->assertTrue($entity->metadata->draft);
    }

    /**
     * Test that provided values override the default.
     */
    public function testProvidedValuesOverrideDefault(): void {
        $entity = EntityWithDefaultChild::from([
            'title' => 'Test Article',
            'metadata' => [
                'version' => '2.0',
                'draft' => false,
            ],
        ]);

        $this->assertSame('Test Article', $entity->title);
        $this->assertInstanceOf(DefaultMetadataEntity::class, $entity->metadata);
        $this->assertSame('2.0', $entity->metadata->version);
        $this->assertFalse($entity->metadata->draft);
    }

    /**
     * Test that nullable properties with EntityDefaultInterface get the default when not provided.
     */
    public function testNullablePropertyGetsDefault(): void {
        $entity = EntityWithDefaultChild::fromValidated([
            'title' => 'Test Article',
        ]);

        // optionalMetadata should get the default since it implements EntityDefaultInterface
        $this->assertInstanceOf(DefaultMetadataEntity::class, $entity->optionalMetadata);
        $this->assertSame('1.0', $entity->optionalMetadata->version);
    }

    /**
     * Test that nullable properties can still be explicitly set to null.
     */
    public function testNullablePropertyCanBeNull(): void {
        $entity = EntityWithDefaultChild::fromValidated([
            'title' => 'Test Article',
            'optionalMetadata' => null,
        ]);

        $this->assertNull($entity->optionalMetadata);
    }

    /**
     * Test that toArray includes the default values.
     */
    public function testToArrayIncludesDefaults(): void {
        $entity = EntityWithDefaultChild::fromValidated([
            'title' => 'Test Article',
        ]);

        $array = $entity->toArray();

        $this->assertEquals([
            'title' => 'Test Article',
            'metadata' => [
                'version' => '1.0',
                'draft' => true,
            ],
            'optionalMetadata' => [
                'version' => '1.0',
                'draft' => true,
            ],
        ], $array);
    }

    /**
     * Test that each call to default() returns a new instance.
     */
    public function testDefaultReturnsNewInstance(): void {
        $default1 = DefaultMetadataEntity::default();
        $default2 = DefaultMetadataEntity::default();

        $this->assertNotSame($default1, $default2);

        // Modify one to ensure they're independent
        $default1->version = '2.0';
        $this->assertSame('1.0', $default2->version);
    }

    /**
     * Test that property is required in schema even when it has a default.
     *
     * Properties with defaults should be marked as required because the schema
     * will apply the default, ensuring they're always present in validated data.
     */
    public function testPropertyWithDefaultIsRequired(): void {
        $schema = EntityWithDefaultChild::getSchema();
        $schemaArray = $schema->getSchemaArray();

        $required = $schemaArray['required'] ?? [];

        // All non-nullable properties should be required, even if they have defaults
        $this->assertContains('title', $required);
        $this->assertContains('metadata', $required); // Has default via EntityDefaultInterface

        // Nullable properties should not be required
        $this->assertNotContains('optionalMetadata', $required);
    }
}
