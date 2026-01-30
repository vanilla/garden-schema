<?php
/**
 * @author Adam Charron <adam@vanillaforums.com>
 * @copyright 2009-2024 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema\Tests;

use Garden\Schema\Entity;
use Garden\Schema\SchemaVariant;
use Garden\Schema\Tests\Fixtures\AuthorEntity;
use Garden\Schema\Tests\Fixtures\PostEntity;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the #[NestedVariant] attribute functionality.
 */
class NestedVariantTest extends TestCase {
    protected function setUp(): void {
        Entity::invalidateSchemaCache();
    }

    //
    // Basic NestedVariant Tests
    //

    public function testNestedVariantSerializesChildAsFragment(): void {
        $author = new AuthorEntity();
        $author->id = 1;
        $author->name = 'John Doe';
        $author->email = 'john@example.com';
        $author->bio = 'A long biography that should be excluded in Fragment';
        $author->avatarUrl = 'https://example.com/avatar.jpg';

        $post = new PostEntity();
        $post->id = 100;
        $post->title = 'My Post';
        $post->content = 'Full post content';
        $post->author = $author;

        // Serialize post as Full
        $array = $post->toArray(SchemaVariant::Full);

        // Post should have full content
        $this->assertArrayHasKey('content', $array);
        $this->assertSame('Full post content', $array['content']);

        // Author should be Fragment (no bio)
        $this->assertArrayHasKey('author', $array);
        $this->assertIsArray($array['author']);
        $this->assertArrayHasKey('id', $array['author']);
        $this->assertArrayHasKey('name', $array['author']);
        $this->assertArrayHasKey('email', $array['author']);
        $this->assertArrayHasKey('avatarUrl', $array['author']);
        $this->assertArrayNotHasKey('bio', $array['author'], 'Author bio should be excluded (Fragment variant)');
    }

    public function testNestedVariantWithNullChild(): void {
        $post = new PostEntity();
        $post->id = 100;
        $post->title = 'My Post';
        $post->content = 'Content';
        $post->author = null;

        $array = $post->toArray(SchemaVariant::Full);

        $this->assertArrayHasKey('author', $array);
        $this->assertNull($array['author']);
    }

    public function testNestedVariantWithArrayOfEntities(): void {
        $author1 = new AuthorEntity();
        $author1->id = 1;
        $author1->name = 'Author One';
        $author1->email = 'one@example.com';
        $author1->bio = 'Bio one';

        $author2 = new AuthorEntity();
        $author2->id = 2;
        $author2->name = 'Author Two';
        $author2->email = 'two@example.com';
        $author2->bio = 'Bio two';

        $post = new PostEntity();
        $post->id = 100;
        $post->title = 'Post with Comments';
        $post->commentAuthors = [$author1, $author2];

        $array = $post->toArray(SchemaVariant::Full);

        // Comment authors should be Fragment (no bio)
        $this->assertArrayHasKey('commentAuthors', $array);
        $this->assertCount(2, $array['commentAuthors']);

        foreach ($array['commentAuthors'] as $i => $authorArray) {
            $this->assertArrayHasKey('id', $authorArray);
            $this->assertArrayHasKey('name', $authorArray);
            $this->assertArrayNotHasKey('bio', $authorArray, "Comment author {$i} should not have bio (Fragment variant)");
        }
    }

    //
    // Schema Generation Tests
    //

    public function testSchemaReflectsNestedVariant(): void {
        $schema = PostEntity::getSchema(SchemaVariant::Full);
        $properties = $schema->getSchemaArray()['properties'];

        // Post should have content in Full
        $this->assertArrayHasKey('content', $properties);

        // Author property should have Fragment schema (no bio property)
        $this->assertArrayHasKey('author', $properties);
        $authorSchema = $properties['author'];

        $this->assertArrayHasKey('properties', $authorSchema);
        $this->assertArrayHasKey('id', $authorSchema['properties']);
        $this->assertArrayHasKey('name', $authorSchema['properties']);
        $this->assertArrayHasKey('email', $authorSchema['properties']);
        $this->assertArrayHasKey('avatarUrl', $authorSchema['properties']);
        $this->assertArrayNotHasKey('bio', $authorSchema['properties'], 'Author schema should use Fragment variant (no bio)');
    }

    public function testSchemaFragmentStillUsesNestedFragment(): void {
        // When post is Fragment, author should still be Fragment (as specified by NestedVariant)
        $schema = PostEntity::getSchema(SchemaVariant::Fragment);
        $properties = $schema->getSchemaArray()['properties'];

        // Post Fragment should not have content
        $this->assertArrayNotHasKey('content', $properties);

        // Author should still be Fragment
        $this->assertArrayHasKey('author', $properties);
        $authorSchema = $properties['author'];

        $this->assertArrayNotHasKey('bio', $authorSchema['properties']);
    }

    public function testNestedVariantAlwaysUsesSpecifiedVariant(): void {
        $author = new AuthorEntity();
        $author->id = 1;
        $author->name = 'Test';
        $author->email = 'test@example.com';
        $author->bio = 'Bio text';

        $post = new PostEntity();
        $post->id = 1;
        $post->title = 'Test';
        $post->author = $author;

        // Even when serializing as different variants, author always uses Fragment
        $fullArray = $post->toArray(SchemaVariant::Full);
        $this->assertArrayNotHasKey('bio', $fullArray['author']);

        $fragmentArray = $post->toArray(SchemaVariant::Fragment);
        $this->assertArrayNotHasKey('bio', $fragmentArray['author']);

        $mutableArray = $post->toArray(SchemaVariant::Mutable);
        $this->assertArrayNotHasKey('bio', $mutableArray['author']);
    }

    //
    // Without NestedVariant (baseline)
    //

    public function testWithoutNestedVariantChildInheritsParent(): void {
        // AuthorEntity standalone - Full should include bio
        $author = new AuthorEntity();
        $author->id = 1;
        $author->name = 'Test';
        $author->email = 'test@example.com';
        $author->bio = 'Full bio';

        $fullArray = $author->toArray(SchemaVariant::Full);
        $this->assertArrayHasKey('bio', $fullArray);

        $fragmentArray = $author->toArray(SchemaVariant::Fragment);
        $this->assertArrayNotHasKey('bio', $fragmentArray);
    }

    //
    // JSON Serialization Tests
    //

    public function testJsonSerializeRespectsNestedVariant(): void {
        $author = new AuthorEntity();
        $author->id = 1;
        $author->name = 'John';
        $author->email = 'john@example.com';
        $author->bio = 'Bio text';

        $post = new PostEntity();
        $post->id = 100;
        $post->title = 'Test';
        $post->author = $author;
        $post->setSerializationVariant(SchemaVariant::Full);

        $json = json_encode($post);
        $decoded = json_decode($json, true);

        $this->assertArrayHasKey('content', $decoded);
        $this->assertArrayHasKey('author', $decoded);
        $this->assertArrayNotHasKey('bio', $decoded['author']);
    }

    //
    // Entity::from() Tests
    //

    public function testFromValidationUsesNestedVariantSchema(): void {
        // When creating a PostEntity via from(), the nested author should validate against Fragment schema
        $post = PostEntity::from([
            'id' => 1,
            'title' => 'Test Post',
            'content' => 'Content',
            'author' => [
                'id' => 10,
                'name' => 'Author Name',
                'email' => 'author@example.com',
                // bio is not provided - should be OK since Fragment schema doesn't require it
            ],
        ], SchemaVariant::Full);

        $this->assertInstanceOf(PostEntity::class, $post);
        $this->assertInstanceOf(AuthorEntity::class, $post->author);
        $this->assertSame(10, $post->author->id);
        $this->assertSame('', $post->author->bio); // Default empty string
    }

    //
    // Caching Tests
    //

    public function testSchemaCachingWithNestedVariants(): void {
        // Both schemas should be cached independently
        $fullSchema1 = PostEntity::getSchema(SchemaVariant::Full);
        $fragmentSchema1 = PostEntity::getSchema(SchemaVariant::Fragment);
        $fullSchema2 = PostEntity::getSchema(SchemaVariant::Full);
        $fragmentSchema2 = PostEntity::getSchema(SchemaVariant::Fragment);

        $this->assertSame($fullSchema1, $fullSchema2);
        $this->assertSame($fragmentSchema1, $fragmentSchema2);
        $this->assertNotSame($fullSchema1, $fragmentSchema1);
    }

    public function testNestedVariantAttributeEntityClassName(): void {
        $schema = PostEntity::getSchema(SchemaVariant::Full);
        $properties = $schema->getSchemaArray()['properties'];

        // Author should still have entityClassName for hydration
        $this->assertArrayHasKey('entityClassName', $properties['author']);
        $this->assertSame(AuthorEntity::class, $properties['author']['entityClassName']);
    }
}
