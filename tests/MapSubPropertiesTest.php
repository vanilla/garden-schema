<?php

namespace Garden\Schema\Tests;

use Garden\Schema\Entity;
use Garden\Schema\MapSubProperties;
use Garden\Schema\Tests\Fixtures\BlogPostEntity;
use Garden\Schema\Tests\Fixtures\SimpleAuthorEntity;
use PHPUnit\Framework\TestCase;

/**
 * Tests for MapSubProperties attribute functionality.
 */
class MapSubPropertiesTest extends TestCase {
    public function testMapSubPropertiesCopiesFlatKeys(): void {
        $entity = BlogPostEntity::from([
            'postID' => 1,
            'title' => 'My Article',
            'authorID' => 123,
            'authorName' => 'John Doe',
        ]);

        $this->assertSame(123, $entity->author->authorID);
        $this->assertSame('John Doe', $entity->author->authorName);
    }

    public function testMapSubPropertiesCopiesNestedPathsToFlatTargets(): void {
        $entity = BlogPostEntity::from([
            'postID' => 2,
            'title' => 'Another Article',
            'authorID' => 456,
            'authorName' => 'Jane Doe',
            'metadata' => [
                'authorEmail' => 'jane@example.com',
                'authorBio' => 'A prolific writer',
            ],
        ]);

        $this->assertSame('jane@example.com', $entity->author->email);
        $this->assertSame('A prolific writer', $entity->author->bio);
    }

    public function testMapSubPropertiesCombinesFlatKeysAndMappings(): void {
        $entity = BlogPostEntity::from([
            'postID' => 3,
            'title' => 'Full Article',
            'authorID' => 789,
            'authorName' => 'Bob Smith',
            'metadata' => [
                'authorEmail' => 'bob@example.com',
                'authorBio' => 'Tech enthusiast',
            ],
        ]);

        // All author properties should be populated
        $this->assertSame(789, $entity->author->authorID);
        $this->assertSame('Bob Smith', $entity->author->authorName);
        $this->assertSame('bob@example.com', $entity->author->email);
        $this->assertSame('Tech enthusiast', $entity->author->bio);
    }

    public function testMapSubPropertiesSkipsMissingSourcePaths(): void {
        // metadata is missing, so email and bio should remain null
        $entity = BlogPostEntity::from([
            'postID' => 4,
            'title' => 'Minimal Article',
            'authorID' => 111,
            'authorName' => 'Alice',
        ]);

        $this->assertSame(111, $entity->author->authorID);
        $this->assertSame('Alice', $entity->author->authorName);
        $this->assertNull($entity->author->email);
        $this->assertNull($entity->author->bio);
    }

    public function testMapSubPropertiesSkipsMissingNestedPaths(): void {
        // metadata exists but doesn't have authorEmail
        $entity = BlogPostEntity::from([
            'postID' => 5,
            'title' => 'Partial Article',
            'authorID' => 222,
            'authorName' => 'Charlie',
            'metadata' => [
                'authorBio' => 'Minimalist',
            ],
        ]);

        $this->assertSame(222, $entity->author->authorID);
        $this->assertSame('Charlie', $entity->author->authorName);
        $this->assertNull($entity->author->email);
        $this->assertSame('Minimalist', $entity->author->bio);
    }

    public function testMapSubPropertiesPreservesOriginalData(): void {
        // Original data should not be modified (values are copied, not moved)
        // This test verifies that both the mapped nested property and original locations work
        $inputData = [
            'postID' => 6,
            'title' => 'Test Article',
            'authorID' => 333,
            'authorName' => 'Dan',
            'metadata' => [
                'authorEmail' => 'dan@example.com',
            ],
        ];

        $entity = BlogPostEntity::from($inputData);

        // Entity should have the data in the mapped nested structure
        $this->assertSame(333, $entity->author->authorID);
        $this->assertSame('dan@example.com', $entity->author->email);

        // Verify entity's main properties are intact
        $this->assertSame(6, $entity->postID);
        $this->assertSame('Test Article', $entity->title);
    }

    public function testMapSubPropertiesWithNestedTargetPath(): void {
        // Test entity that maps to nested target paths
        $testEntity = new class extends Entity {
            public int $id;

            #[MapSubProperties(
                keys: [],
                mapping: ['user.profile.displayName' => 'profile.name', 'user.profile.avatarUrl' => 'profile.avatar']
            )]
            public \ArrayObject $settings;
        };

        $entity = $testEntity::from([
            'id' => 1,
            'user' => [
                'profile' => [
                    'displayName' => 'TestUser',
                    'avatarUrl' => 'https://example.com/avatar.jpg',
                ],
            ],
        ]);

        $this->assertInstanceOf(\ArrayObject::class, $entity->settings);
        $this->assertSame('TestUser', $entity->settings['profile']['name']);
        $this->assertSame('https://example.com/avatar.jpg', $entity->settings['profile']['avatar']);
    }

    public function testMapSubPropertiesWithDotNotationInKeys(): void {
        // Test that keys also support dot notation
        $testEntity = new class extends Entity {
            public int $id;

            #[MapSubProperties(
                keys: ['user.id', 'user.name'],
                mapping: []
            )]
            public \ArrayObject $userData;
        };

        $entity = $testEntity::from([
            'id' => 1,
            'user' => [
                'id' => 42,
                'name' => 'NestedUser',
            ],
        ]);

        $this->assertInstanceOf(\ArrayObject::class, $entity->userData);
        $this->assertSame(42, $entity->userData['user']['id']);
        $this->assertSame('NestedUser', $entity->userData['user']['name']);
    }

    public function testMapSubPropertiesAddsToExistingData(): void {
        // If the target property already has data, MapSubProperties should add to it
        $testEntity = new class extends Entity {
            public int $id;

            #[MapSubProperties(
                keys: ['extra'],
                mapping: []
            )]
            public \ArrayObject $config;
        };

        $entity = $testEntity::from([
            'id' => 1,
            'config' => ['existing' => 'value'],
            'extra' => 'additional',
        ]);

        $this->assertSame('value', $entity->config['existing']);
        $this->assertSame('additional', $entity->config['extra']);
    }

    /**
     * Test that the entity can be serialized to an array and back to an alt array and the data is the same.
     */
    public function testInOutToAltArray(): void {
        $input = [
            // Top level on blog post
            "postID" => 1,
            "title" => "My Article",

            // Data that gets mapped in during nesting.
            "authorID" => 123,
            "authorName" => "John Doe",
            "metadata" => [
                "authorEmail" => "john@example.com",
                "authorBio" => "A prolific writer",
            ],
        ];

        $expectedToArray = [
            "postID" => 1,
            "title" => "My Article",
            "author" => [
                "authorID" => 123,
                "authorName" => "John Doe",
                "email" => "john@example.com",
                "bio" => "A prolific writer",
            ],
        ];

        $expectedToAltArray = $input;

        $entity = BlogPostEntity::from($input);

        $this->assertEquals($expectedToArray, $entity->toArray());
        $this->assertEquals($expectedToAltArray, $entity->toAltArray());
    }
}
