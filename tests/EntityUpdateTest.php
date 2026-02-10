<?php

namespace Garden\Schema\Tests;

use Garden\Schema\Entity;
use Garden\Schema\EntityFieldFormat;
use Garden\Schema\ValidationException;
use Garden\Schema\Tests\Fixtures\ArticleEntity;
use Garden\Schema\Tests\Fixtures\AltNamesEntity;
use Garden\Schema\Tests\Fixtures\BlogPostEntity;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Entity::update() and Entity::getUpdatedArray().
 */
class EntityUpdateTest extends TestCase {

    protected function setUp(): void {
        Entity::invalidateSchemaCache();
    }

    /**
     * Create a fully populated ArticleEntity for testing.
     */
    private function createArticle(): ArticleEntity {
        return ArticleEntity::from([
            'id' => 1,
            'title' => 'Original Title',
            'slug' => 'original-title',
            'body' => 'Original body content.',
            'excerpt' => 'Original excerpt.',
            'createdAt' => '2024-01-01T00:00:00+00:00',
            'updatedAt' => '2024-01-01T00:00:00+00:00',
            'authorId' => 100,
            'tags' => ['php', 'testing'],
        ]);
    }

    //
    // Basic update behavior
    //

    public function testUpdateSetsProperties(): void {
        $article = $this->createArticle();
        $article->update(['title' => 'Updated Title', 'body' => 'Updated body.']);

        $this->assertSame('Updated Title', $article->title);
        $this->assertSame('Updated body.', $article->body);
        $this->assertSame('original-title', $article->slug); // unchanged
        $this->assertSame(1, $article->id); // unchanged
    }

    public function testUpdateReturnsSelf(): void {
        $article = $this->createArticle();
        $result = $article->update(['title' => 'Updated']);

        $this->assertSame($article, $result);
    }

    public function testUpdateWithEmptyArray(): void {
        $article = $this->createArticle();
        $article->update([]);

        $this->assertSame([], $article->getUpdatedArray());
        $this->assertSame('Original Title', $article->title);
    }

    //
    // Updated field tracking
    //

    public function testGetUpdatedArrayReturnsOnlyUpdatedFields(): void {
        $article = $this->createArticle();
        $article->update(['title' => 'Updated Title']);

        $updated = $article->getUpdatedArray();
        $this->assertSame(['title' => 'Updated Title'], $updated);
    }

    public function testMultipleUpdatesCumulativeTracking(): void {
        $article = $this->createArticle();
        $article->update(['title' => 'First Update']);
        $article->update(['body' => 'Second Update']);

        $updated = $article->getUpdatedArray();
        $this->assertSame([
            'title' => 'First Update',
            'body' => 'Second Update',
        ], $updated);
    }

    public function testUpdatingSameFieldTwiceTracksOnce(): void {
        $article = $this->createArticle();
        $article->update(['title' => 'First']);
        $article->update(['title' => 'Second']);

        $updated = $article->getUpdatedArray();
        $this->assertSame(['title' => 'Second'], $updated);
    }

    public function testGetUpdatedArrayDefaultFormatIsCanonical(): void {
        $article = $this->createArticle();
        $article->update(['title' => 'Updated']);

        $default = $article->getUpdatedArray();
        $canonical = $article->getUpdatedArray(EntityFieldFormat::Canonical);
        $this->assertSame($default, $canonical);
    }

    //
    // Non-mutable fields are ignored
    //

    public function testUpdateIgnoresNonMutableFields(): void {
        $article = $this->createArticle();
        // id, createdAt, updatedAt, authorId are excluded from Mutable variant
        $article->update(['title' => 'Updated']);

        $this->assertSame(1, $article->id);
        $this->assertSame('Updated', $article->title);

        $updated = $article->getUpdatedArray();
        $this->assertArrayHasKey('title', $updated);
        $this->assertArrayNotHasKey('id', $updated);
    }

    public function testUpdateStripsNonMutableFieldsSilently(): void {
        $article = $this->createArticle();
        // Passing id (non-mutable) along with title (mutable)
        $article->update(['id' => 999, 'title' => 'Updated']);

        // id is silently stripped - only mutable fields are applied
        $this->assertSame(1, $article->id);
        $this->assertSame('Updated', $article->title);
    }

    //
    // Validation
    //

    public function testUpdateValidatesInput(): void {
        $article = $this->createArticle();

        $this->expectException(ValidationException::class);
        $article->update(['title' => ['not', 'a', 'string']]);
    }

    //
    // Alt name support
    //

    public function testUpdateWithAltNames(): void {
        $entity = AltNamesEntity::from(['name' => 'John', 'count' => 1]);
        $entity->update(['user_name' => 'Jane']);

        $this->assertSame('Jane', $entity->name);

        $updated = $entity->getUpdatedArray();
        $this->assertSame(['name' => 'Jane'], $updated);
    }

    public function testGetUpdatedArrayPrimaryAltNameFormat(): void {
        $entity = AltNamesEntity::from(['name' => 'John', 'count' => 1]);
        $entity->update(['name' => 'Jane']);

        $updated = $entity->getUpdatedArray(EntityFieldFormat::PrimaryAltName);
        $this->assertSame(['user_name' => 'Jane'], $updated);
    }

    public function testUpdateWithAltNameAndGetAltFormat(): void {
        $entity = AltNamesEntity::from(['name' => 'John', 'email' => 'john@example.com', 'count' => 1]);
        $entity->update(['user_name' => 'Jane', 'e-mail' => 'jane@example.com']);

        // Canonical format uses property names
        $canonical = $entity->getUpdatedArray(EntityFieldFormat::Canonical);
        $this->assertSame([
            'name' => 'Jane',
            'email' => 'jane@example.com',
        ], $canonical);

        // PrimaryAltName format uses alt names
        $alt = $entity->getUpdatedArray(EntityFieldFormat::PrimaryAltName);
        $this->assertSame([
            'user_name' => 'Jane',
            'e-mail' => 'jane@example.com',
        ], $alt);
    }

    //
    // MapSubProperties support
    //

    public function testUpdateSimpleFieldOnEntityWithMapSubProperties(): void {
        $post = BlogPostEntity::from([
            'postID' => 1,
            'title' => 'Original',
            'authorID' => 100,
            'authorName' => 'John',
        ]);

        $post->update(['title' => 'Updated Title']);

        $this->assertSame('Updated Title', $post->title);
        $this->assertSame(100, $post->author->authorID); // unchanged

        $updated = $post->getUpdatedArray();
        $this->assertSame(['title' => 'Updated Title'], $updated);
    }

    public function testUpdateWithMappedSubPropertyKeys(): void {
        $post = BlogPostEntity::from([
            'postID' => 1,
            'title' => 'Original',
            'authorID' => 100,
            'authorName' => 'John',
        ]);

        // Update via mapped sub-property keys
        $post->update(['authorID' => 200, 'authorName' => 'Jane']);

        $this->assertSame(200, $post->author->authorID);
        $this->assertSame('Jane', $post->author->authorName);

        // The tracked field is the canonical property 'author'
        $updated = $post->getUpdatedArray();
        $this->assertArrayHasKey('author', $updated);
        $this->assertSame(200, $updated['author']['authorID']);
        $this->assertSame('Jane', $updated['author']['authorName']);
    }

    //
    // Value type conversion in getUpdatedArray
    //

    public function testGetUpdatedArrayConvertsComplexTypes(): void {
        $article = $this->createArticle();
        $article->update(['tags' => ['new-tag-1', 'new-tag-2']]);

        $updated = $article->getUpdatedArray();
        $this->assertSame(['tags' => ['new-tag-1', 'new-tag-2']], $updated);
    }
}
