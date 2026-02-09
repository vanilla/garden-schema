<?php

namespace Garden\Schema\Tests;

use Garden\Schema\Entity;
use Garden\Schema\EntityFieldFormat;
use Garden\Schema\MapSubProperties;
use Garden\Schema\PropertyAltNames;
use Garden\Schema\Tests\Fixtures\AltNamesEntity;
use Garden\Schema\Tests\Fixtures\BlogPostEntity;
use Garden\Schema\Tests\Fixtures\DotNotationEntity;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Entity::convertFieldName() and Entity::convertFieldNames().
 */
class ConvertFieldNameTest extends TestCase {

    //
    // convertFieldName() tests - Canonical to PrimaryAltName
    //

    public function testConvertFieldNameCanonicalToAlt(): void {
        // AltNamesEntity has: #[PropertyAltNames(['user_name', 'userName', 'uname'], primaryAltName: 'user_name')]
        $result = AltNamesEntity::convertFieldName('name', EntityFieldFormat::PrimaryAltName);
        $this->assertSame('user_name', $result);
    }

    public function testConvertFieldNameCanonicalToAltEmail(): void {
        // AltNamesEntity has: #[PropertyAltNames(['e-mail', 'emailAddress'], primaryAltName: 'e-mail')]
        $result = AltNamesEntity::convertFieldName('email', EntityFieldFormat::PrimaryAltName);
        $this->assertSame('e-mail', $result);
    }

    public function testConvertFieldNameCanonicalToAltSingleString(): void {
        // AltNamesEntity has: #[PropertyAltNames('nick_name')]
        $result = AltNamesEntity::convertFieldName('nickname', EntityFieldFormat::PrimaryAltName);
        $this->assertSame('nick_name', $result);
    }

    public function testConvertFieldNameCanonicalToAltNoMapping(): void {
        // 'count' has no PropertyAltNames attribute, should return unchanged
        $result = AltNamesEntity::convertFieldName('count', EntityFieldFormat::PrimaryAltName);
        $this->assertSame('count', $result);
    }

    public function testConvertFieldNameCanonicalToAltUnknownField(): void {
        // Unknown field should return unchanged
        $result = AltNamesEntity::convertFieldName('nonexistent', EntityFieldFormat::PrimaryAltName);
        $this->assertSame('nonexistent', $result);
    }

    //
    // convertFieldName() tests - PrimaryAltName to Canonical
    //

    public function testConvertFieldNameAltToCanonical(): void {
        $result = AltNamesEntity::convertFieldName('user_name', EntityFieldFormat::Canonical);
        $this->assertSame('name', $result);
    }

    public function testConvertFieldNameAltToCanonicalEmail(): void {
        $result = AltNamesEntity::convertFieldName('e-mail', EntityFieldFormat::Canonical);
        $this->assertSame('email', $result);
    }

    public function testConvertFieldNameAltToCanonicalSingleString(): void {
        $result = AltNamesEntity::convertFieldName('nick_name', EntityFieldFormat::Canonical);
        $this->assertSame('nickname', $result);
    }

    public function testConvertFieldNameAltToCanonicalNoMapping(): void {
        // 'count' is already canonical, should return unchanged
        $result = AltNamesEntity::convertFieldName('count', EntityFieldFormat::Canonical);
        $this->assertSame('count', $result);
    }

    public function testConvertFieldNameAltToCanonicalUnknownField(): void {
        $result = AltNamesEntity::convertFieldName('nonexistent', EntityFieldFormat::Canonical);
        $this->assertSame('nonexistent', $result);
    }

    //
    // convertFieldName() with dot notation alt names
    //

    public function testConvertFieldNameDotNotationCanonicalToAlt(): void {
        // DotNotationEntity has: #[PropertyAltNames(['attributes.displayName', ...], primaryAltName: 'attributes.displayName')]
        $result = DotNotationEntity::convertFieldName('displayName', EntityFieldFormat::PrimaryAltName);
        $this->assertSame('attributes.displayName', $result);
    }

    public function testConvertFieldNameDotNotationAltToCanonical(): void {
        $result = DotNotationEntity::convertFieldName('attributes.displayName', EntityFieldFormat::Canonical);
        $this->assertSame('displayName', $result);
    }

    public function testConvertFieldNameDeepDotNotation(): void {
        // DotNotationEntity: #[PropertyAltNames(['settings.preferences.theme', 'config.theme'], primaryAltName: 'settings.preferences.theme')]
        $result = DotNotationEntity::convertFieldName('theme', EntityFieldFormat::PrimaryAltName);
        $this->assertSame('settings.preferences.theme', $result);

        $result = DotNotationEntity::convertFieldName('settings.preferences.theme', EntityFieldFormat::Canonical);
        $this->assertSame('theme', $result);
    }

    //
    // convertFieldNames() tests (batch conversion)
    //

    public function testConvertFieldNamesCanonicalToAlt(): void {
        $result = AltNamesEntity::convertFieldNames(
            ['name', 'email', 'nickname', 'count'],
            EntityFieldFormat::PrimaryAltName,
        );

        $this->assertSame(['user_name', 'e-mail', 'nick_name', 'count'], $result);
    }

    public function testConvertFieldNamesAltToCanonical(): void {
        $result = AltNamesEntity::convertFieldNames(
            ['user_name', 'e-mail', 'nick_name', 'count'],
            EntityFieldFormat::Canonical,
        );

        $this->assertSame(['name', 'email', 'nickname', 'count'], $result);
    }

    public function testConvertFieldNamesEmptyArray(): void {
        $result = AltNamesEntity::convertFieldNames([], EntityFieldFormat::PrimaryAltName);
        $this->assertSame([], $result);
    }

    public function testConvertFieldNamesMixedKnownAndUnknown(): void {
        $result = AltNamesEntity::convertFieldNames(
            ['name', 'unknown', 'email'],
            EntityFieldFormat::PrimaryAltName,
        );

        $this->assertSame(['user_name', 'unknown', 'e-mail'], $result);
    }

    //
    // Round-trip tests
    //

    public function testConvertFieldNameRoundTrip(): void {
        // Canonical -> Alt -> Canonical should return the original name
        $altName = AltNamesEntity::convertFieldName('name', EntityFieldFormat::PrimaryAltName);
        $canonical = AltNamesEntity::convertFieldName($altName, EntityFieldFormat::Canonical);
        $this->assertSame('name', $canonical);
    }

    public function testConvertFieldNamesRoundTrip(): void {
        $fields = ['name', 'email', 'nickname', 'count'];
        $altNames = AltNamesEntity::convertFieldNames($fields, EntityFieldFormat::PrimaryAltName);
        $canonical = AltNamesEntity::convertFieldNames($altNames, EntityFieldFormat::Canonical);
        $this->assertSame($fields, $canonical);
    }

    //
    // Entity with MapSubProperties (convertFieldName only uses PropertyAltNames)
    //

    public function testConvertFieldNameMapSubPropertiesPropertyUnchanged(): void {
        // BlogPostEntity has MapSubProperties on 'author' but no PropertyAltNames
        // convertFieldName should return the property name unchanged since MapSubProperties
        // is a structural transformation, not a name transformation
        $result = BlogPostEntity::convertFieldName('author', EntityFieldFormat::PrimaryAltName);
        $this->assertSame('author', $result);

        $result = BlogPostEntity::convertFieldName('postID', EntityFieldFormat::PrimaryAltName);
        $this->assertSame('postID', $result);
    }

    //
    // Cache invalidation tests
    //

    public function testConvertFieldNameAfterCacheInvalidation(): void {
        // Prime the cache
        $result1 = AltNamesEntity::convertFieldName('name', EntityFieldFormat::PrimaryAltName);
        $this->assertSame('user_name', $result1);

        // Invalidate cache for this class
        AltNamesEntity::invalidateSchemaCache(AltNamesEntity::class);

        // Should still work (cache is rebuilt)
        $result2 = AltNamesEntity::convertFieldName('name', EntityFieldFormat::PrimaryAltName);
        $this->assertSame('user_name', $result2);
    }

    public function testConvertFieldNameAfterFullCacheInvalidation(): void {
        // Prime the cache
        AltNamesEntity::convertFieldName('name', EntityFieldFormat::PrimaryAltName);
        DotNotationEntity::convertFieldName('displayName', EntityFieldFormat::PrimaryAltName);

        // Invalidate all caches
        Entity::invalidateSchemaCache();

        // Should still work after full invalidation
        $result1 = AltNamesEntity::convertFieldName('name', EntityFieldFormat::PrimaryAltName);
        $this->assertSame('user_name', $result1);

        $result2 = DotNotationEntity::convertFieldName('displayName', EntityFieldFormat::PrimaryAltName);
        $this->assertSame('attributes.displayName', $result2);
    }

    //
    // Entity with combined PropertyAltNames and MapSubProperties
    //

    public function testConvertFieldNameWithBothAttributes(): void {
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

        // PropertyAltNames should work
        $result = $testEntity::convertFieldName('name', EntityFieldFormat::PrimaryAltName);
        $this->assertSame('user_name', $result);

        // MapSubProperties property has no alt name
        $result = $testEntity::convertFieldName('profile', EntityFieldFormat::PrimaryAltName);
        $this->assertSame('profile', $result);

        // Regular property with no attributes
        $result = $testEntity::convertFieldName('id', EntityFieldFormat::PrimaryAltName);
        $this->assertSame('id', $result);

        // Map sub property nested field.
        $result = $testEntity::convertFieldName('profileId', EntityFieldFormat::Canonical);
        $this->assertSame('profile.profileId', $result);

        $result = $testEntity::convertFieldName('meta.bio', EntityFieldFormat::Canonical);
        $this->assertSame('profile.biography', $result);

        $result = $testEntity::convertFieldName('profile.biography', EntityFieldFormat::Canonical);
        $this->assertSame('profile.biography', $result);

        $result = $testEntity::convertFieldName('profile.biography', EntityFieldFormat::PrimaryAltName);
        $this->assertSame('meta.bio', $result);



    }
}
