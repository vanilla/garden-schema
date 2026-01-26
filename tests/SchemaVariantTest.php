<?php
/**
 * @author Adam Charron <adam@vanillaforums.com>
 * @copyright 2009-2024 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema\Tests;

use Garden\Schema\Entity;
use Garden\Schema\SchemaVariant;
use Garden\Schema\Tests\Fixtures\ArticleEntity;
use Garden\Schema\Tests\Fixtures\MultiExcludeEntity;
use PHPUnit\Framework\TestCase;

/**
 * Tests for schema variant functionality.
 */
class SchemaVariantTest extends TestCase {
    protected function setUp(): void {
        // Clear schema cache before each test
        Entity::invalidateSchemaCache();
    }

    //
    // Full Variant Tests
    //

    public function testFullSchemaIncludesAllStandardProperties(): void {
        $schema = ArticleEntity::getSchema(SchemaVariant::Full);
        $properties = $schema->getSchemaArray()['properties'];

        // Standard properties
        $this->assertArrayHasKey('id', $properties);
        $this->assertArrayHasKey('title', $properties);
        $this->assertArrayHasKey('slug', $properties);
        $this->assertArrayHasKey('body', $properties);
        $this->assertArrayHasKey('excerpt', $properties);
        $this->assertArrayHasKey('createdAt', $properties);
        $this->assertArrayHasKey('updatedAt', $properties);
        $this->assertArrayHasKey('authorId', $properties);
        $this->assertArrayHasKey('tags', $properties);

        // IncludeOnlyInVariant with Full specified
        $this->assertArrayHasKey('inviteCode', $properties);

        // IncludeOnlyInVariant without Full - should NOT be present
        $this->assertArrayNotHasKey('initialPassword', $properties);
    }

    public function testDefaultVariantIsFull(): void {
        $defaultSchema = ArticleEntity::getSchema();
        $fullSchema = ArticleEntity::getSchema(SchemaVariant::Full);

        $this->assertEquals(
            $defaultSchema->getSchemaArray(),
            $fullSchema->getSchemaArray()
        );
    }

    //
    // Fragment Variant Tests
    //

    public function testFragmentSchemaExcludesLargeFields(): void {
        $schema = ArticleEntity::getSchema(SchemaVariant::Fragment);
        $properties = $schema->getSchemaArray()['properties'];

        // Should be included
        $this->assertArrayHasKey('id', $properties);
        $this->assertArrayHasKey('title', $properties);
        $this->assertArrayHasKey('slug', $properties);
        $this->assertArrayHasKey('createdAt', $properties);
        $this->assertArrayHasKey('updatedAt', $properties);
        $this->assertArrayHasKey('authorId', $properties);
        $this->assertArrayHasKey('tags', $properties);

        // Should be excluded (marked with ExcludeFromVariant::Fragment)
        $this->assertArrayNotHasKey('body', $properties);
        $this->assertArrayNotHasKey('excerpt', $properties);

        // IncludeOnlyInVariant fields should NOT be present
        $this->assertArrayNotHasKey('initialPassword', $properties);
        $this->assertArrayNotHasKey('inviteCode', $properties);
    }

    public function testFragmentSchemaRequiredOnlyIncludesIncludedProperties(): void {
        $schema = ArticleEntity::getSchema(SchemaVariant::Fragment);
        $required = $schema->getSchemaArray()['required'] ?? [];

        // body is required in Full but excluded from Fragment
        $this->assertNotContains('body', $required);

        // title is required and included in Fragment
        $this->assertContains('title', $required);
        $this->assertContains('id', $required);
    }

    //
    // Mutable Variant Tests
    //

    public function testMutableSchemaExcludesSystemFields(): void {
        $schema = ArticleEntity::getSchema(SchemaVariant::Mutable);
        $properties = $schema->getSchemaArray()['properties'];

        // Should be included (user-modifiable)
        $this->assertArrayHasKey('title', $properties);
        $this->assertArrayHasKey('slug', $properties);
        $this->assertArrayHasKey('body', $properties);
        $this->assertArrayHasKey('excerpt', $properties);
        $this->assertArrayHasKey('tags', $properties);

        // Should be excluded (system-managed, not user-mutable)
        $this->assertArrayNotHasKey('id', $properties);
        $this->assertArrayNotHasKey('createdAt', $properties);
        $this->assertArrayNotHasKey('updatedAt', $properties);
        $this->assertArrayNotHasKey('authorId', $properties);

        // IncludeOnlyInVariant fields should NOT be present
        $this->assertArrayNotHasKey('initialPassword', $properties);
        $this->assertArrayNotHasKey('inviteCode', $properties);
    }

    //
    // Create Variant Tests
    //

    public function testCreateSchemaIncludesCreateOnlyFields(): void {
        $schema = ArticleEntity::getSchema(SchemaVariant::Create);
        $properties = $schema->getSchemaArray()['properties'];

        // Standard properties (not excluded from Create)
        $this->assertArrayHasKey('id', $properties);
        $this->assertArrayHasKey('title', $properties);
        $this->assertArrayHasKey('slug', $properties);
        $this->assertArrayHasKey('body', $properties);
        $this->assertArrayHasKey('createdAt', $properties);
        $this->assertArrayHasKey('authorId', $properties);

        // Create-only fields (IncludeOnlyInVariant::Create)
        $this->assertArrayHasKey('initialPassword', $properties);
        $this->assertArrayHasKey('inviteCode', $properties);
    }

    //
    // Multiple ExcludeFromVariant Tests
    //

    public function testMultipleExcludeFromVariantAttributes(): void {
        $fullSchema = MultiExcludeEntity::getSchema(SchemaVariant::Full);
        $fragmentSchema = MultiExcludeEntity::getSchema(SchemaVariant::Fragment);
        $mutableSchema = MultiExcludeEntity::getSchema(SchemaVariant::Mutable);
        $createSchema = MultiExcludeEntity::getSchema(SchemaVariant::Create);

        // Full should have everything
        $fullProps = $fullSchema->getSchemaArray()['properties'];
        $this->assertArrayHasKey('id', $fullProps);
        $this->assertArrayHasKey('name', $fullProps);
        $this->assertArrayHasKey('sensitiveData', $fullProps);
        $this->assertArrayHasKey('internalOnly', $fullProps);

        // Fragment excludes sensitiveData and internalOnly
        $fragmentProps = $fragmentSchema->getSchemaArray()['properties'];
        $this->assertArrayHasKey('id', $fragmentProps);
        $this->assertArrayHasKey('name', $fragmentProps);
        $this->assertArrayNotHasKey('sensitiveData', $fragmentProps);
        $this->assertArrayNotHasKey('internalOnly', $fragmentProps);

        // Mutable excludes sensitiveData and internalOnly
        $mutableProps = $mutableSchema->getSchemaArray()['properties'];
        $this->assertArrayHasKey('id', $mutableProps);
        $this->assertArrayHasKey('name', $mutableProps);
        $this->assertArrayNotHasKey('sensitiveData', $mutableProps);
        $this->assertArrayNotHasKey('internalOnly', $mutableProps);

        // Create excludes internalOnly only
        $createProps = $createSchema->getSchemaArray()['properties'];
        $this->assertArrayHasKey('id', $createProps);
        $this->assertArrayHasKey('name', $createProps);
        $this->assertArrayHasKey('sensitiveData', $createProps);
        $this->assertArrayNotHasKey('internalOnly', $createProps);
    }

    public function testSingleAttributeWithMultipleVariants(): void {
        // internalOnly is excluded from Fragment, Mutable, and Create in a single attribute
        $fullSchema = MultiExcludeEntity::getSchema(SchemaVariant::Full);
        $this->assertArrayHasKey('internalOnly', $fullSchema->getSchemaArray()['properties']);

        foreach ([SchemaVariant::Fragment, SchemaVariant::Mutable, SchemaVariant::Create] as $variant) {
            $schema = MultiExcludeEntity::getSchema($variant);
            $this->assertArrayNotHasKey(
                'internalOnly',
                $schema->getSchemaArray()['properties'],
                "internalOnly should be excluded from {$variant->value}"
            );
        }
    }

    //
    // Caching Tests
    //

    public function testSchemaVariantsAreCachedSeparately(): void {
        $full1 = ArticleEntity::getSchema(SchemaVariant::Full);
        $fragment1 = ArticleEntity::getSchema(SchemaVariant::Fragment);
        $full2 = ArticleEntity::getSchema(SchemaVariant::Full);
        $fragment2 = ArticleEntity::getSchema(SchemaVariant::Fragment);

        // Same variant should return same cached instance
        $this->assertSame($full1, $full2);
        $this->assertSame($fragment1, $fragment2);

        // Different variants should be different instances
        $this->assertNotSame($full1, $fragment1);
    }

    public function testInvalidateSchemaCacheForSpecificVariant(): void {
        $full1 = ArticleEntity::getSchema(SchemaVariant::Full);
        $fragment1 = ArticleEntity::getSchema(SchemaVariant::Fragment);

        // Invalidate only Full variant
        Entity::invalidateSchemaCache(ArticleEntity::class, SchemaVariant::Full);

        $full2 = ArticleEntity::getSchema(SchemaVariant::Full);
        $fragment2 = ArticleEntity::getSchema(SchemaVariant::Fragment);

        // Full should be new instance
        $this->assertNotSame($full1, $full2);

        // Fragment should be same cached instance
        $this->assertSame($fragment1, $fragment2);
    }

    public function testInvalidateSchemaCacheForAllVariantsOfClass(): void {
        $full1 = ArticleEntity::getSchema(SchemaVariant::Full);
        $fragment1 = ArticleEntity::getSchema(SchemaVariant::Fragment);

        // Invalidate all variants for ArticleEntity
        Entity::invalidateSchemaCache(ArticleEntity::class);

        $full2 = ArticleEntity::getSchema(SchemaVariant::Full);
        $fragment2 = ArticleEntity::getSchema(SchemaVariant::Fragment);

        // Both should be new instances
        $this->assertNotSame($full1, $full2);
        $this->assertNotSame($fragment1, $fragment2);
    }

    public function testInvalidateSchemaCacheGlobally(): void {
        $articleFull = ArticleEntity::getSchema(SchemaVariant::Full);
        $multiFull = MultiExcludeEntity::getSchema(SchemaVariant::Full);

        // Invalidate all caches
        Entity::invalidateSchemaCache();

        $articleFull2 = ArticleEntity::getSchema(SchemaVariant::Full);
        $multiFull2 = MultiExcludeEntity::getSchema(SchemaVariant::Full);

        $this->assertNotSame($articleFull, $articleFull2);
        $this->assertNotSame($multiFull, $multiFull2);
    }

    //
    // Schema Validation Tests
    //

    public function testValidationWithFragmentSchema(): void {
        $schema = ArticleEntity::getSchema(SchemaVariant::Fragment);

        // Should validate without body (excluded from fragment)
        $result = $schema->validate([
            'id' => 1,
            'title' => 'Test Article',
            'slug' => 'test-article',
            'createdAt' => '2024-01-01T00:00:00+00:00',
            'updatedAt' => '2024-01-01T00:00:00+00:00',
            'authorId' => 42,
            'tags' => ['test'],
        ]);

        $this->assertSame(1, $result['id']);
        $this->assertSame('Test Article', $result['title']);
    }

    public function testValidationWithMutableSchema(): void {
        $schema = ArticleEntity::getSchema(SchemaVariant::Mutable);

        // Should validate with only mutable fields
        $result = $schema->validate([
            'title' => 'Updated Title',
            'slug' => 'updated-slug',
            'body' => 'Updated body content',
            'tags' => ['updated', 'tags'],
        ]);

        $this->assertSame('Updated Title', $result['title']);
        $this->assertSame('Updated body content', $result['body']);
    }

    public function testValidationWithCreateSchema(): void {
        $schema = ArticleEntity::getSchema(SchemaVariant::Create);

        // Should accept create-only fields
        $result = $schema->validate([
            'id' => 1,
            'title' => 'New Article',
            'slug' => 'new-article',
            'body' => 'Article content',
            'createdAt' => '2024-01-01T00:00:00+00:00',
            'updatedAt' => '2024-01-01T00:00:00+00:00',
            'authorId' => 42,
            'tags' => [],
            'initialPassword' => 'secret123',
            'inviteCode' => 'INVITE-ABC',
        ]);

        $this->assertSame('secret123', $result['initialPassword']);
        $this->assertSame('INVITE-ABC', $result['inviteCode']);
    }

    //
    // SchemaVariant Enum Tests
    //

    public function testSchemaVariantEnumValues(): void {
        $this->assertSame('full', SchemaVariant::Full->value);
        $this->assertSame('fragment', SchemaVariant::Fragment->value);
        $this->assertSame('mutable', SchemaVariant::Mutable->value);
        $this->assertSame('create', SchemaVariant::Create->value);
    }

    public function testSchemaVariantEnumCases(): void {
        $cases = SchemaVariant::cases();

        $this->assertCount(4, $cases);
        $this->assertContains(SchemaVariant::Full, $cases);
        $this->assertContains(SchemaVariant::Fragment, $cases);
        $this->assertContains(SchemaVariant::Mutable, $cases);
        $this->assertContains(SchemaVariant::Create, $cases);
    }
}
