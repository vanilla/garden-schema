<?php

namespace Garden\Schema\Tests;

use Garden\Schema\Entity;
use Garden\Schema\MapSubProperties;
use Garden\Schema\PropertyAltNames;
use Garden\Schema\Tests\Fixtures\AltNamesEntity;
use Garden\Schema\Tests\Fixtures\BlogPostEntity;
use Garden\Schema\Tests\Fixtures\DotNotationEntity;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Entity::toAltArray() functionality.
 */
class ToAltArrayTest extends TestCase {

    //
    // PropertyAltNames reverse mapping tests
    //

    public function testToAltArrayWithSimpleAltName(): void {
        $entity = AltNamesEntity::from([
            'name' => 'John',
            'count' => 5,
        ]);

        $altArray = $entity->toAltArray();

        // name should be serialized as user_name (the primary alt name)
        $this->assertArrayHasKey('user_name', $altArray);
        $this->assertSame('John', $altArray['user_name']);
        $this->assertArrayNotHasKey('name', $altArray);

        // count has no alt name, should remain as is
        $this->assertArrayHasKey('count', $altArray);
        $this->assertSame(5, $altArray['count']);
    }

    public function testToAltArrayWithMultipleAltNameProperties(): void {
        $entity = AltNamesEntity::from([
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'nickname' => 'JJ',
            'count' => 10,
        ]);

        $altArray = $entity->toAltArray();

        // name -> user_name
        $this->assertArrayHasKey('user_name', $altArray);
        $this->assertSame('Jane', $altArray['user_name']);

        // email -> e-mail
        $this->assertArrayHasKey('e-mail', $altArray);
        $this->assertSame('jane@example.com', $altArray['e-mail']);

        // nickname -> nick_name
        $this->assertArrayHasKey('nick_name', $altArray);
        $this->assertSame('JJ', $altArray['nick_name']);

        // count has no alt name
        $this->assertArrayHasKey('count', $altArray);
        $this->assertSame(10, $altArray['count']);
    }

    public function testToAltArrayWithDotNotation(): void {
        $entity = DotNotationEntity::from([
            'displayName' => 'Test User',
            'theme' => 'dark',
            'id' => 1,
        ]);

        $altArray = $entity->toAltArray();

        // displayName should be nested at attributes.displayName
        $this->assertArrayHasKey('attributes', $altArray);
        $this->assertSame('Test User', $altArray['attributes']['displayName']);
        $this->assertArrayNotHasKey('displayName', $altArray);

        // theme should be nested at settings.preferences.theme
        $this->assertArrayHasKey('settings', $altArray);
        $this->assertSame('dark', $altArray['settings']['preferences']['theme']);
        $this->assertArrayNotHasKey('theme', $altArray);

        // id has no alt name
        $this->assertArrayHasKey('id', $altArray);
        $this->assertSame(1, $altArray['id']);
    }

    public function testToAltArrayWithDeepNestedDotNotation(): void {
        $entity = DotNotationEntity::from([
            'displayName' => 'Test',
            'deepValue' => 'found it!',
            'id' => 2,
        ]);

        $altArray = $entity->toAltArray();

        // deepValue should be nested at deeply.nested.value.here
        $this->assertArrayHasKey('deeply', $altArray);
        $this->assertSame('found it!', $altArray['deeply']['nested']['value']['here']);
        $this->assertArrayNotHasKey('deepValue', $altArray);
    }

    public function testToAltArrayWithDotNotationDisabled(): void {
        $entity = DotNotationEntity::from([
            'displayName' => 'Test',
            'noDotNotation' => 'literal value',
            'id' => 3,
        ]);

        $altArray = $entity->toAltArray();

        // noDotNotation should use literal key simple_name (not nested)
        $this->assertArrayHasKey('simple_name', $altArray);
        $this->assertSame('literal value', $altArray['simple_name']);
        $this->assertArrayNotHasKey('noDotNotation', $altArray);
    }

    public function testToAltArrayWithNullValues(): void {
        $entity = AltNamesEntity::from([
            'name' => 'Test',
            'email' => null,
            'count' => 1,
        ]);

        $altArray = $entity->toAltArray();

        // null values should still be mapped
        $this->assertArrayHasKey('e-mail', $altArray);
        $this->assertNull($altArray['e-mail']);
    }

    //
    // MapSubProperties reverse mapping tests
    //

    public function testToAltArrayWithMapSubPropertiesFlatKeys(): void {
        $entity = BlogPostEntity::from([
            'postID' => 1,
            'title' => 'My Article',
            'authorID' => 123,
            'authorName' => 'John Doe',
        ]);

        $altArray = $entity->toAltArray();

        // authorID and authorName should be extracted back to root level
        $this->assertArrayHasKey('authorID', $altArray);
        $this->assertSame(123, $altArray['authorID']);

        $this->assertArrayHasKey('authorName', $altArray);
        $this->assertSame('John Doe', $altArray['authorName']);

        // The author property itself should be removed (it was constructed)
        $this->assertArrayNotHasKey('author', $altArray);

        // Regular properties should remain
        $this->assertArrayHasKey('postID', $altArray);
        $this->assertSame(1, $altArray['postID']);
        $this->assertArrayHasKey('title', $altArray);
        $this->assertSame('My Article', $altArray['title']);
    }

    public function testToAltArrayWithMapSubPropertiesMappings(): void {
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

        $altArray = $entity->toAltArray();

        // Mapped values should be extracted to their source paths
        $this->assertArrayHasKey('metadata', $altArray);
        $this->assertSame('jane@example.com', $altArray['metadata']['authorEmail']);
        $this->assertSame('A prolific writer', $altArray['metadata']['authorBio']);

        // Flat keys should also be extracted
        $this->assertArrayHasKey('authorID', $altArray);
        $this->assertSame(456, $altArray['authorID']);
        $this->assertArrayHasKey('authorName', $altArray);
        $this->assertSame('Jane Doe', $altArray['authorName']);
    }

    public function testToAltArrayWithMapSubPropertiesNullOptionalValues(): void {
        $entity = BlogPostEntity::from([
            'postID' => 3,
            'title' => 'Minimal Article',
            'authorID' => 111,
            'authorName' => 'Alice',
        ]);

        $altArray = $entity->toAltArray();

        // Optional values (email, bio) are null on the entity, so they get extracted as null
        // This creates the metadata structure with null values
        $this->assertArrayHasKey('metadata', $altArray);
        $this->assertNull($altArray['metadata']['authorEmail']);
        $this->assertNull($altArray['metadata']['authorBio']);
    }

    //
    // Round-trip tests
    //

    public function testRoundTripWithAltNames(): void {
        $originalData = [
            'user_name' => 'John',
            'e-mail' => 'john@example.com',
            'nick_name' => 'Johnny',
            'count' => 42,
        ];

        // Decode from alt names
        $entity = AltNamesEntity::from($originalData);

        // Encode back to alt names
        $altArray = $entity->toAltArray();

        // Should match original data (only with the values that were set)
        $this->assertSame('John', $altArray['user_name']);
        $this->assertSame('john@example.com', $altArray['e-mail']);
        $this->assertSame('Johnny', $altArray['nick_name']);
        $this->assertSame(42, $altArray['count']);
    }

    public function testRoundTripWithDotNotation(): void {
        $originalData = [
            'attributes' => [
                'displayName' => 'Test User',
            ],
            'settings' => [
                'preferences' => [
                    'theme' => 'dark',
                ],
            ],
            'id' => 1,
        ];

        // Decode from nested structure
        $entity = DotNotationEntity::from($originalData);

        // Encode back to nested structure
        $altArray = $entity->toAltArray();

        // Should recreate the nested structure
        $this->assertSame('Test User', $altArray['attributes']['displayName']);
        $this->assertSame('dark', $altArray['settings']['preferences']['theme']);
        $this->assertSame(1, $altArray['id']);
    }

    public function testRoundTripWithMapSubProperties(): void {
        $originalData = [
            'postID' => 1,
            'title' => 'Test Article',
            'authorID' => 999,
            'authorName' => 'Test Author',
            'metadata' => [
                'authorEmail' => 'test@example.com',
                'authorBio' => 'Test bio',
            ],
        ];

        // Decode (MapSubProperties constructs the author property)
        $entity = BlogPostEntity::from($originalData);

        // Encode back (toAltArray should extract back to original structure)
        $altArray = $entity->toAltArray();

        // Should recreate the original structure
        $this->assertSame(1, $altArray['postID']);
        $this->assertSame('Test Article', $altArray['title']);
        $this->assertSame(999, $altArray['authorID']);
        $this->assertSame('Test Author', $altArray['authorName']);
        $this->assertSame('test@example.com', $altArray['metadata']['authorEmail']);
        $this->assertSame('Test bio', $altArray['metadata']['authorBio']);
        $this->assertArrayNotHasKey('author', $altArray);
    }

    //
    // Combined tests (both PropertyAltNames and MapSubProperties)
    //

    public function testToAltArrayWithBothAltNamesAndMapSubProperties(): void {
        // Create an entity that uses both attributes
        $testEntity = new class extends Entity {
            #[PropertyAltNames(['user_name', 'userName'], primaryAltName: 'user_name')]
            public string $name;

            #[MapSubProperties(
                keys: ['profileId'],
                mapping: ['meta.bio' => 'biography']
            )]
            public \ArrayObject $profile;

            public int $id;
        };

        $entity = $testEntity::from([
            'name' => 'Test User',
            'profileId' => 123,
            'meta' => ['bio' => 'A test bio'],
            'id' => 1,
        ]);

        $altArray = $entity->toAltArray();

        // PropertyAltNames should rename name -> user_name
        $this->assertArrayHasKey('user_name', $altArray);
        $this->assertSame('Test User', $altArray['user_name']);
        $this->assertArrayNotHasKey('name', $altArray);

        // MapSubProperties should extract profile data back
        $this->assertArrayHasKey('profileId', $altArray);
        $this->assertSame(123, $altArray['profileId']);
        $this->assertArrayHasKey('meta', $altArray);
        $this->assertSame('A test bio', $altArray['meta']['bio']);
        $this->assertArrayNotHasKey('profile', $altArray);

        // Regular property should remain
        $this->assertArrayHasKey('id', $altArray);
        $this->assertSame(1, $altArray['id']);
    }

    //
    // Error case tests
    //

    public function testPropertyAltNamesRequiresPrimaryAltNameForMultiple(): void {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('When multiple alt names are provided, primaryAltName must be specified');

        // This should throw because we have multiple alt names but no primaryAltName
        new PropertyAltNames(['name1', 'name2']);
    }

    public function testPropertyAltNamesPrimaryMustBeInList(): void {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("primaryAltName 'invalid' must be one of the provided alt names");

        new PropertyAltNames(['name1', 'name2'], primaryAltName: 'invalid');
    }

    public function testPropertyAltNamesSingleInferssPrimary(): void {
        $attr = new PropertyAltNames('single_name');

        $this->assertSame('single_name', $attr->getPrimaryAltName());
        $this->assertSame(['single_name'], $attr->getAltNames());
    }

    public function testPropertyAltNamesSingleArrayInfersPrimary(): void {
        $attr = new PropertyAltNames(['only_one']);

        $this->assertSame('only_one', $attr->getPrimaryAltName());
        $this->assertSame(['only_one'], $attr->getAltNames());
    }
}
