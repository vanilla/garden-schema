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
use Garden\Schema\Tests\Fixtures\ChildEntity;
use Garden\Schema\Tests\Fixtures\CustomVariant;
use Garden\Schema\Tests\Fixtures\CustomVariantEntity;
use Garden\Schema\Tests\Fixtures\MultiExcludeEntity;
use Garden\Schema\Tests\Fixtures\NestedVariantChildEntity;
use Garden\Schema\Tests\Fixtures\ParentWithNestedVariantEntity;
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
        $this->assertSame('internal', SchemaVariant::Internal->value);
    }

    public function testSchemaVariantEnumCases(): void {
        $cases = SchemaVariant::cases();

        $this->assertCount(5, $cases);
        $this->assertContains(SchemaVariant::Full, $cases);
        $this->assertContains(SchemaVariant::Fragment, $cases);
        $this->assertContains(SchemaVariant::Mutable, $cases);
        $this->assertContains(SchemaVariant::Create, $cases);
        $this->assertContains(SchemaVariant::Internal, $cases);
    }

    //
    // Custom Variant Enum Tests
    //

    public function testCustomVariantEnumWithGetSchema(): void {
        $publicSchema = CustomVariantEntity::getSchema(CustomVariant::Public);
        $adminSchema = CustomVariantEntity::getSchema(CustomVariant::Admin);
        $internalSchema = CustomVariantEntity::getSchema(CustomVariant::Internal);

        // Public should not have adminNotes or internalSecret
        $publicProps = $publicSchema->getSchemaArray()['properties'];
        $this->assertArrayHasKey('id', $publicProps);
        $this->assertArrayHasKey('name', $publicProps);
        $this->assertArrayNotHasKey('adminNotes', $publicProps);
        $this->assertArrayNotHasKey('internalSecret', $publicProps);

        // Admin should have adminNotes but not internalSecret
        $adminProps = $adminSchema->getSchemaArray()['properties'];
        $this->assertArrayHasKey('id', $adminProps);
        $this->assertArrayHasKey('name', $adminProps);
        $this->assertArrayHasKey('adminNotes', $adminProps);
        $this->assertArrayNotHasKey('internalSecret', $adminProps);

        // Internal should have everything
        $internalProps = $internalSchema->getSchemaArray()['properties'];
        $this->assertArrayHasKey('id', $internalProps);
        $this->assertArrayHasKey('name', $internalProps);
        $this->assertArrayHasKey('adminNotes', $internalProps);
        $this->assertArrayHasKey('internalSecret', $internalProps);
    }

    public function testCustomVariantCachingSeparate(): void {
        $public1 = CustomVariantEntity::getSchema(CustomVariant::Public);
        $admin1 = CustomVariantEntity::getSchema(CustomVariant::Admin);
        $public2 = CustomVariantEntity::getSchema(CustomVariant::Public);

        $this->assertSame($public1, $public2);
        $this->assertNotSame($public1, $admin1);
    }

    public function testCustomVariantInvalidateCacheSpecific(): void {
        $public1 = CustomVariantEntity::getSchema(CustomVariant::Public);

        Entity::invalidateSchemaCache(CustomVariantEntity::class, CustomVariant::Public);

        $public2 = CustomVariantEntity::getSchema(CustomVariant::Public);

        $this->assertNotSame($public1, $public2);
    }

    //
    // Serialization Variant Tests
    //

    public function testToArrayWithVariant(): void {
        $entity = new CustomVariantEntity();
        $entity->id = 1;
        $entity->name = 'Test';
        $entity->adminNotes = 'Admin only note';
        $entity->internalSecret = 'Top secret';

        // Public variant should not include adminNotes or internalSecret
        $publicArray = $entity->toArray(CustomVariant::Public);
        $this->assertArrayHasKey('id', $publicArray);
        $this->assertArrayHasKey('name', $publicArray);
        $this->assertArrayNotHasKey('adminNotes', $publicArray);
        $this->assertArrayNotHasKey('internalSecret', $publicArray);

        // Admin variant should include adminNotes
        $adminArray = $entity->toArray(CustomVariant::Admin);
        $this->assertArrayHasKey('adminNotes', $adminArray);
        $this->assertArrayNotHasKey('internalSecret', $adminArray);

        // Internal variant should include everything
        $internalArray = $entity->toArray(CustomVariant::Internal);
        $this->assertArrayHasKey('adminNotes', $internalArray);
        $this->assertArrayHasKey('internalSecret', $internalArray);
    }

    public function testSetSerializationVariant(): void {
        $entity = new CustomVariantEntity();
        $entity->id = 1;
        $entity->name = 'Test';
        $entity->adminNotes = 'Secret';
        $entity->internalSecret = 'Top secret';

        // Set serialization variant
        $entity->setSerializationVariant(CustomVariant::Public);

        // toArray() without argument should use the set variant
        $array = $entity->toArray();
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayNotHasKey('adminNotes', $array);
        $this->assertArrayNotHasKey('internalSecret', $array);
    }

    public function testGetSerializationVariant(): void {
        $entity = new CustomVariantEntity();

        $this->assertNull($entity->getSerializationVariant());

        $entity->setSerializationVariant(CustomVariant::Admin);
        $this->assertSame(CustomVariant::Admin, $entity->getSerializationVariant());

        $entity->setSerializationVariant(null);
        $this->assertNull($entity->getSerializationVariant());
    }

    public function testJsonSerializeUsesSerializationVariant(): void {
        $entity = new CustomVariantEntity();
        $entity->id = 1;
        $entity->name = 'Test';
        $entity->adminNotes = 'Secret';
        $entity->internalSecret = 'Top secret';

        // Without variant, should include all (via Full schema which has no exclusions for these)
        $json = json_encode($entity);
        $decoded = json_decode($json, true);
        $this->assertArrayHasKey('adminNotes', $decoded);

        // With variant set
        $entity->setSerializationVariant(CustomVariant::Public);
        $json = json_encode($entity);
        $decoded = json_decode($json, true);
        $this->assertArrayNotHasKey('adminNotes', $decoded);
        $this->assertArrayNotHasKey('internalSecret', $decoded);
    }

    public function testToArrayVariantOverridesSerializationVariant(): void {
        $entity = new CustomVariantEntity();
        $entity->id = 1;
        $entity->name = 'Test';
        $entity->adminNotes = 'Secret';

        // Set serialization variant to Public
        $entity->setSerializationVariant(CustomVariant::Public);

        // But explicitly request Admin variant in toArray
        $array = $entity->toArray(CustomVariant::Admin);
        $this->assertArrayHasKey('adminNotes', $array);
    }

    //
    // Nested Entity Serialization Tests
    //

    public function testNestedEntitySerializationWithVariant(): void {
        $child = new ChildEntity();
        $child->id = 'child-1';

        $parent = new CustomVariantEntity();
        $parent->id = 1;
        $parent->name = 'Parent';
        $parent->adminNotes = 'Admin note';
        $parent->child = $child;

        // Serialize with Public variant
        $array = $parent->toArray(CustomVariant::Public);

        $this->assertArrayHasKey('child', $array);
        $this->assertIsArray($array['child']);
        $this->assertSame('child-1', $array['child']['id']);
        $this->assertArrayNotHasKey('adminNotes', $array);
    }

    public function testNestedEntityArraySerializationWithVariant(): void {
        // Use ArticleEntity with SchemaVariant to test nested arrays
        $fragmentArray = ArticleEntity::getSchema(SchemaVariant::Fragment)->getSchemaArray();

        // Fragment should not have body
        $this->assertArrayNotHasKey('body', $fragmentArray['properties']);
    }

    //
    // Internal Variant Tests
    //

    public function testInternalVariantIncludesAllStandardProperties(): void {
        $schema = ArticleEntity::getSchema(SchemaVariant::Internal);
        $properties = $schema->getSchemaArray()['properties'];

        // Internal should include standard properties
        $this->assertArrayHasKey('id', $properties);
        $this->assertArrayHasKey('title', $properties);
        $this->assertArrayHasKey('body', $properties);
        $this->assertArrayHasKey('createdAt', $properties);
    }

    //
    // Fluent API Tests
    //

    public function testSetSerializationVariantReturnsSelf(): void {
        $entity = new CustomVariantEntity();
        $result = $entity->setSerializationVariant(CustomVariant::Public);

        $this->assertSame($entity, $result);
    }

    public function testFluentSetSerializationVariantChaining(): void {
        $entity = new CustomVariantEntity();
        $entity->id = 1;
        $entity->name = 'Test';
        $entity->adminNotes = 'Secret';

        $array = $entity
            ->setSerializationVariant(CustomVariant::Public)
            ->toArray();

        $this->assertArrayNotHasKey('adminNotes', $array);
    }

    //
    // ArrayAccess Tests (independent of serialization variant)
    //

    public function testArrayAccessIgnoresSerializationVariant(): void {
        $entity = new CustomVariantEntity();
        $entity->id = 1;
        $entity->name = 'Test';
        $entity->adminNotes = 'Secret';

        // Set a restrictive variant
        $entity->setSerializationVariant(CustomVariant::Public);

        // ArrayAccess should still work for all properties regardless of variant
        $this->assertTrue(isset($entity['adminNotes']));
        $this->assertSame('Secret', $entity['adminNotes']);

        // Can still set properties not in the variant
        $entity['adminNotes'] = 'Updated';
        $this->assertSame('Updated', $entity->adminNotes);

        // But toArray() respects the variant
        $array = $entity->toArray();
        $this->assertArrayNotHasKey('adminNotes', $array);
    }

    public function testArrayAccessWorksWithPublicProperties(): void {
        $entity = new CustomVariantEntity();
        $entity->id = 1;
        $entity->name = 'Test';

        // All public properties are accessible
        $this->assertTrue(isset($entity['id']));
        $this->assertTrue(isset($entity['name']));
        $this->assertTrue(isset($entity['adminNotes'])); // Has default value

        $entity['adminNotes'] = 'Note';
        $this->assertSame('Note', $entity['adminNotes']);
    }

    //
    // Nested Entity Schema with Variants Tests
    //

    public function testGetSchemaPassesVariantToNestedEntitySchema(): void {
        // Public variant should exclude adminOnlyField and internalOnlyField from nested child
        $publicSchema = ParentWithNestedVariantEntity::getSchema(CustomVariant::Public);
        $publicProps = $publicSchema->getSchemaArray()['properties'];

        // Parent fields
        $this->assertArrayHasKey('id', $publicProps);
        $this->assertArrayHasKey('name', $publicProps);
        $this->assertArrayNotHasKey('parentAdminField', $publicProps);
        $this->assertArrayNotHasKey('parentInternalField', $publicProps);

        // Child entity schema should also be Public variant
        $this->assertArrayHasKey('child', $publicProps);
        $childSchema = $publicProps['child'];
        $this->assertArrayHasKey('properties', $childSchema);
        $childProps = $childSchema['properties'];

        $this->assertArrayHasKey('id', $childProps);
        $this->assertArrayHasKey('name', $childProps);
        $this->assertArrayNotHasKey('adminOnlyField', $childProps);
        $this->assertArrayNotHasKey('internalOnlyField', $childProps);
    }

    public function testGetSchemaPassesVariantToNestedEntitySchemaAdmin(): void {
        // Admin variant should include adminOnlyField but not internalOnlyField
        $adminSchema = ParentWithNestedVariantEntity::getSchema(CustomVariant::Admin);
        $adminProps = $adminSchema->getSchemaArray()['properties'];

        // Parent fields
        $this->assertArrayHasKey('id', $adminProps);
        $this->assertArrayHasKey('name', $adminProps);
        $this->assertArrayHasKey('parentAdminField', $adminProps);
        $this->assertArrayNotHasKey('parentInternalField', $adminProps);

        // Child entity schema should also be Admin variant
        $childSchema = $adminProps['child'];
        $childProps = $childSchema['properties'];

        $this->assertArrayHasKey('id', $childProps);
        $this->assertArrayHasKey('name', $childProps);
        $this->assertArrayHasKey('adminOnlyField', $childProps);
        $this->assertArrayNotHasKey('internalOnlyField', $childProps);
    }

    public function testGetSchemaPassesVariantToNestedEntitySchemaInternal(): void {
        // Internal variant should include all fields
        $internalSchema = ParentWithNestedVariantEntity::getSchema(CustomVariant::Internal);
        $internalProps = $internalSchema->getSchemaArray()['properties'];

        // Parent fields
        $this->assertArrayHasKey('id', $internalProps);
        $this->assertArrayHasKey('name', $internalProps);
        $this->assertArrayHasKey('parentAdminField', $internalProps);
        $this->assertArrayHasKey('parentInternalField', $internalProps);

        // Child entity schema should also be Internal variant
        $childSchema = $internalProps['child'];
        $childProps = $childSchema['properties'];

        $this->assertArrayHasKey('id', $childProps);
        $this->assertArrayHasKey('name', $childProps);
        $this->assertArrayHasKey('adminOnlyField', $childProps);
        $this->assertArrayHasKey('internalOnlyField', $childProps);
    }

    public function testNestedEntityArraySchemaGetsVariant(): void {
        // Check that array of nested entities also gets the variant
        $publicSchema = ParentWithNestedVariantEntity::getSchema(CustomVariant::Public);
        $publicProps = $publicSchema->getSchemaArray()['properties'];

        $this->assertArrayHasKey('children', $publicProps);
        $childrenSchema = $publicProps['children'];

        // The items schema should have the Public variant applied
        $this->assertArrayHasKey('items', $childrenSchema);
        $itemSchema = $childrenSchema['items'];

        // Items should not have adminOnlyField or internalOnlyField in Public variant
        // The entityClassName should still be present for hydration
        $this->assertArrayHasKey('entityClassName', $itemSchema);
        $this->assertSame(NestedVariantChildEntity::class, $itemSchema['entityClassName']);
    }

    //
    // Entity::from() with Variant Tests
    //

    public function testFromWithVariantValidatesAgainstVariantSchema(): void {
        // Public variant should not require parentAdminField
        $entity = ParentWithNestedVariantEntity::from([
            'id' => 1,
            'name' => 'Test',
            // parentAdminField is not provided (excluded from Public variant)
        ], CustomVariant::Public);

        $this->assertInstanceOf(ParentWithNestedVariantEntity::class, $entity);
        $this->assertSame(1, $entity->id);
        $this->assertSame('Test', $entity->name);
        // parentAdminField should have its default value
        $this->assertSame('', $entity->parentAdminField);
    }

    public function testFromWithVariantPassesVariantToNestedEntity(): void {
        // When using Public variant, nested child should also use Public variant validation
        $entity = ParentWithNestedVariantEntity::from([
            'id' => 1,
            'name' => 'Parent',
            'child' => [
                'id' => 2,
                'name' => 'Child',
                // adminOnlyField is not provided (excluded from Public variant)
            ],
        ], CustomVariant::Public);

        $this->assertInstanceOf(ParentWithNestedVariantEntity::class, $entity);
        $this->assertInstanceOf(NestedVariantChildEntity::class, $entity->child);
        $this->assertSame(2, $entity->child->id);
        $this->assertSame('Child', $entity->child->name);
        // adminOnlyField should have its default value
        $this->assertSame('', $entity->child->adminOnlyField);
    }

    public function testFromWithAdminVariantIncludesAdminFields(): void {
        // Admin variant should allow and include admin fields
        $entity = ParentWithNestedVariantEntity::from([
            'id' => 1,
            'name' => 'Parent',
            'parentAdminField' => 'Admin Note',
            'child' => [
                'id' => 2,
                'name' => 'Child',
                'adminOnlyField' => 'Child Admin Note',
            ],
        ], CustomVariant::Admin);

        $this->assertSame('Admin Note', $entity->parentAdminField);
        $this->assertSame('Child Admin Note', $entity->child->adminOnlyField);
    }

    public function testFromWithInternalVariantIncludesAllFields(): void {
        // Internal variant should allow and include all fields
        $entity = ParentWithNestedVariantEntity::from([
            'id' => 1,
            'name' => 'Parent',
            'parentAdminField' => 'Admin Note',
            'parentInternalField' => 'Internal Note',
            'child' => [
                'id' => 2,
                'name' => 'Child',
                'adminOnlyField' => 'Child Admin Note',
                'internalOnlyField' => 'Child Internal Note',
            ],
        ], CustomVariant::Internal);

        $this->assertSame('Admin Note', $entity->parentAdminField);
        $this->assertSame('Internal Note', $entity->parentInternalField);
        $this->assertSame('Child Admin Note', $entity->child->adminOnlyField);
        $this->assertSame('Child Internal Note', $entity->child->internalOnlyField);
    }

    public function testFromWithVariantAndNestedEntityArray(): void {
        // Test from() with variant and array of nested entities
        $entity = ParentWithNestedVariantEntity::from([
            'id' => 1,
            'name' => 'Parent',
            'children' => [
                ['id' => 10, 'name' => 'Child 1'],
                ['id' => 20, 'name' => 'Child 2'],
            ],
        ], CustomVariant::Public);

        $this->assertCount(2, $entity->children);
        $this->assertInstanceOf(NestedVariantChildEntity::class, $entity->children[0]);
        $this->assertSame(10, $entity->children[0]->id);
        $this->assertSame('Child 1', $entity->children[0]->name);
        $this->assertInstanceOf(NestedVariantChildEntity::class, $entity->children[1]);
        $this->assertSame(20, $entity->children[1]->id);
    }

    public function testFromWithoutVariantUsesFullSchema(): void {
        // Without variant, should use Full schema (default behavior)
        $entity = ParentWithNestedVariantEntity::from([
            'id' => 1,
            'name' => 'Parent',
            'parentAdminField' => 'Admin Note',
            'child' => [
                'id' => 2,
                'name' => 'Child',
                'adminOnlyField' => 'Child Admin Note',
            ],
        ]);

        $this->assertSame('Admin Note', $entity->parentAdminField);
        $this->assertSame('Child Admin Note', $entity->child->adminOnlyField);
    }

    //
    // Entity::validate() with Variant Tests
    //

    public function testValidateWithVariant(): void {
        $entity = new ParentWithNestedVariantEntity();
        $entity->id = 1;
        $entity->name = 'Test';
        $entity->parentAdminField = 'Admin';
        $entity->parentInternalField = 'Internal';

        // Validate with Public variant - should pass even with extra fields
        $validated = $entity->validate(CustomVariant::Public);

        $this->assertInstanceOf(ParentWithNestedVariantEntity::class, $validated);
        $this->assertSame(1, $validated->id);
        $this->assertSame('Test', $validated->name);
    }

    public function testValidateWithVariantAndNestedEntity(): void {
        $child = new NestedVariantChildEntity();
        $child->id = 1;
        $child->name = 'Child';
        $child->adminOnlyField = 'Admin';

        $parent = new ParentWithNestedVariantEntity();
        $parent->id = 1;
        $parent->name = 'Parent';
        $parent->child = $child;

        // Validate with Public variant
        $validated = $parent->validate(CustomVariant::Public);

        $this->assertInstanceOf(ParentWithNestedVariantEntity::class, $validated);
        $this->assertInstanceOf(NestedVariantChildEntity::class, $validated->child);
    }
}
