<?php
/**
 * @author Adam Charron <adam@vanillaforums.com>
 * @copyright 2009-2024 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema\Tests;

use Garden\Schema\EntitySchemaCache;
use Garden\Schema\Schema;
use Garden\Schema\SchemaVariant;
use Garden\Schema\Tests\Fixtures\CustomVariant;
use PHPUnit\Framework\TestCase;

/**
 * Tests for EntitySchemaCache utility class.
 */
class EntitySchemaCacheTest extends TestCase {
    protected function setUp(): void {
        EntitySchemaCache::invalidateAll();
    }

    protected function tearDown(): void {
        EntitySchemaCache::invalidateAll();
    }

    public function testGetOrCreateCachesSchema(): void {
        $callCount = 0;
        $factory = function () use (&$callCount) {
            $callCount++;
            return new Schema(['type' => 'object']);
        };

        $schema1 = EntitySchemaCache::getOrCreate('TestClass', SchemaVariant::Full, $factory);
        $schema2 = EntitySchemaCache::getOrCreate('TestClass', SchemaVariant::Full, $factory);

        $this->assertSame($schema1, $schema2);
        $this->assertSame(1, $callCount, 'Factory should only be called once');
    }

    public function testGetOrCreateSeparatesByClass(): void {
        $schema1 = EntitySchemaCache::getOrCreate('Class1', SchemaVariant::Full, fn() => new Schema(['type' => 'object', 'title' => 'Class1']));
        $schema2 = EntitySchemaCache::getOrCreate('Class2', SchemaVariant::Full, fn() => new Schema(['type' => 'object', 'title' => 'Class2']));

        $this->assertNotSame($schema1, $schema2);
        $this->assertSame('Class1', $schema1->getSchemaArray()['title']);
        $this->assertSame('Class2', $schema2->getSchemaArray()['title']);
    }

    public function testGetOrCreateSeparatesByVariant(): void {
        $fullSchema = EntitySchemaCache::getOrCreate('TestClass', SchemaVariant::Full, fn() => new Schema(['type' => 'object', 'title' => 'Full']));
        $fragmentSchema = EntitySchemaCache::getOrCreate('TestClass', SchemaVariant::Fragment, fn() => new Schema(['type' => 'object', 'title' => 'Fragment']));

        $this->assertNotSame($fullSchema, $fragmentSchema);
        $this->assertSame('Full', $fullSchema->getSchemaArray()['title']);
        $this->assertSame('Fragment', $fragmentSchema->getSchemaArray()['title']);
    }

    public function testGetOrCreateWithCustomVariant(): void {
        $publicSchema = EntitySchemaCache::getOrCreate('TestClass', CustomVariant::Public, fn() => new Schema(['type' => 'object', 'title' => 'Public']));
        $adminSchema = EntitySchemaCache::getOrCreate('TestClass', CustomVariant::Admin, fn() => new Schema(['type' => 'object', 'title' => 'Admin']));

        $this->assertNotSame($publicSchema, $adminSchema);
        $this->assertSame('Public', $publicSchema->getSchemaArray()['title']);
        $this->assertSame('Admin', $adminSchema->getSchemaArray()['title']);
    }

    public function testGetOrCreateDefaultsToFullVariant(): void {
        $schema1 = EntitySchemaCache::getOrCreate('TestClass', null, fn() => new Schema(['type' => 'object']));
        $schema2 = EntitySchemaCache::getOrCreate('TestClass', SchemaVariant::Full, fn() => new Schema(['type' => 'object']));

        $this->assertSame($schema1, $schema2);
    }

    public function testHas(): void {
        $this->assertFalse(EntitySchemaCache::has('TestClass'));

        EntitySchemaCache::getOrCreate('TestClass', SchemaVariant::Full, fn() => new Schema(['type' => 'object']));

        $this->assertTrue(EntitySchemaCache::has('TestClass', SchemaVariant::Full));
        $this->assertTrue(EntitySchemaCache::has('TestClass')); // Defaults to Full
        $this->assertFalse(EntitySchemaCache::has('TestClass', SchemaVariant::Fragment));
    }

    public function testGet(): void {
        $this->assertNull(EntitySchemaCache::get('TestClass'));

        $schema = EntitySchemaCache::getOrCreate('TestClass', SchemaVariant::Full, fn() => new Schema(['type' => 'object']));

        $this->assertSame($schema, EntitySchemaCache::get('TestClass', SchemaVariant::Full));
        $this->assertSame($schema, EntitySchemaCache::get('TestClass')); // Defaults to Full
        $this->assertNull(EntitySchemaCache::get('TestClass', SchemaVariant::Fragment));
    }

    public function testInvalidateSpecificVariant(): void {
        EntitySchemaCache::getOrCreate('TestClass', SchemaVariant::Full, fn() => new Schema(['type' => 'object']));
        EntitySchemaCache::getOrCreate('TestClass', SchemaVariant::Fragment, fn() => new Schema(['type' => 'object']));

        EntitySchemaCache::invalidate('TestClass', SchemaVariant::Full);

        $this->assertFalse(EntitySchemaCache::has('TestClass', SchemaVariant::Full));
        $this->assertTrue(EntitySchemaCache::has('TestClass', SchemaVariant::Fragment));
    }

    public function testInvalidateAllVariantsForClass(): void {
        EntitySchemaCache::getOrCreate('TestClass', SchemaVariant::Full, fn() => new Schema(['type' => 'object']));
        EntitySchemaCache::getOrCreate('TestClass', SchemaVariant::Fragment, fn() => new Schema(['type' => 'object']));
        EntitySchemaCache::getOrCreate('OtherClass', SchemaVariant::Full, fn() => new Schema(['type' => 'object']));

        EntitySchemaCache::invalidate('TestClass');

        $this->assertFalse(EntitySchemaCache::has('TestClass', SchemaVariant::Full));
        $this->assertFalse(EntitySchemaCache::has('TestClass', SchemaVariant::Fragment));
        $this->assertTrue(EntitySchemaCache::has('OtherClass', SchemaVariant::Full));
    }

    public function testInvalidateAll(): void {
        EntitySchemaCache::getOrCreate('Class1', SchemaVariant::Full, fn() => new Schema(['type' => 'object']));
        EntitySchemaCache::getOrCreate('Class2', SchemaVariant::Full, fn() => new Schema(['type' => 'object']));

        EntitySchemaCache::invalidateAll();

        $this->assertFalse(EntitySchemaCache::has('Class1'));
        $this->assertFalse(EntitySchemaCache::has('Class2'));
        $this->assertSame(0, EntitySchemaCache::count());
    }

    public function testCount(): void {
        $this->assertSame(0, EntitySchemaCache::count());

        EntitySchemaCache::getOrCreate('Class1', SchemaVariant::Full, fn() => new Schema(['type' => 'object']));
        $this->assertSame(1, EntitySchemaCache::count());

        EntitySchemaCache::getOrCreate('Class1', SchemaVariant::Fragment, fn() => new Schema(['type' => 'object']));
        $this->assertSame(2, EntitySchemaCache::count());

        EntitySchemaCache::getOrCreate('Class2', SchemaVariant::Full, fn() => new Schema(['type' => 'object']));
        $this->assertSame(3, EntitySchemaCache::count());
    }

    public function testGetAll(): void {
        EntitySchemaCache::getOrCreate('Class1', SchemaVariant::Full, fn() => new Schema(['type' => 'object']));
        EntitySchemaCache::getOrCreate('Class2', SchemaVariant::Fragment, fn() => new Schema(['type' => 'object']));

        $all = EntitySchemaCache::getAll();

        $this->assertCount(2, $all);
        $this->assertArrayHasKey('Class1::Garden\Schema\SchemaVariant::full', $all);
        $this->assertArrayHasKey('Class2::Garden\Schema\SchemaVariant::fragment', $all);
    }

    public function testCircularReferenceDetection(): void {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Circular reference detected');

        EntitySchemaCache::getOrCreate('TestClass', SchemaVariant::Full, function () {
            // Try to get the same schema while building it
            return EntitySchemaCache::getOrCreate('TestClass', SchemaVariant::Full, fn() => new Schema(['type' => 'object']));
        });
    }

    public function testCircularReferenceAllowsDifferentVariants(): void {
        // Building Full variant should allow getting Fragment variant
        $fullSchema = EntitySchemaCache::getOrCreate('TestClass', SchemaVariant::Full, function () {
            // This is allowed - different variant
            $fragmentSchema = EntitySchemaCache::getOrCreate('TestClass', SchemaVariant::Fragment, fn() => new Schema(['type' => 'object', 'title' => 'Fragment']));
            return new Schema(['type' => 'object', 'title' => 'Full']);
        });

        $this->assertSame('Full', $fullSchema->getSchemaArray()['title']);
    }
}
