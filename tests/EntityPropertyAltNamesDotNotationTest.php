<?php

namespace Garden\Schema\Tests;

use Garden\Schema\Tests\Fixtures\DotNotationEntity;
use PHPUnit\Framework\TestCase;

/**
 * Tests for PropertyAltNames dot notation functionality.
 */
class EntityPropertyAltNamesDotNotationTest extends TestCase {
    public function testDotNotationSingleLevel(): void {
        $entity = DotNotationEntity::from([
            'attributes' => [
                'displayName' => 'John Doe',
            ],
            'id' => 1,
        ]);

        $this->assertSame('John Doe', $entity->displayName);
    }

    public function testDotNotationSecondAltName(): void {
        $entity = DotNotationEntity::from([
            'meta' => [
                'name' => 'Jane Doe',
            ],
            'id' => 2,
        ]);

        $this->assertSame('Jane Doe', $entity->displayName);
    }

    public function testDotNotationFallbackToSimpleName(): void {
        // When nested paths don't exist, falls back to simple alt name
        $entity = DotNotationEntity::from([
            'name' => 'Simple Name',
            'id' => 3,
        ]);

        $this->assertSame('Simple Name', $entity->displayName);
    }

    public function testDotNotationMultipleLevels(): void {
        $entity = DotNotationEntity::from([
            'settings' => [
                'preferences' => [
                    'theme' => 'dark',
                ],
            ],
            'displayName' => 'Test User',
            'id' => 4,
        ]);

        $this->assertSame('dark', $entity->theme);
    }

    public function testDotNotationDeeplyNested(): void {
        $entity = DotNotationEntity::from([
            'deeply' => [
                'nested' => [
                    'value' => [
                        'here' => 'found it!',
                    ],
                ],
            ],
            'displayName' => 'Test User',
            'id' => 5,
        ]);

        $this->assertSame('found it!', $entity->deepValue);
    }

    public function testDotNotationFirstMatchWins(): void {
        // First alt name 'attributes.displayName' should win over 'meta.name'
        $entity = DotNotationEntity::from([
            'attributes' => [
                'displayName' => 'First',
            ],
            'meta' => [
                'name' => 'Second',
            ],
            'id' => 6,
        ]);

        $this->assertSame('First', $entity->displayName);
    }

    public function testDotNotationPathDoesNotExist(): void {
        // When nested path doesn't exist, try next alt name
        $entity = DotNotationEntity::from([
            'attributes' => [
                'otherField' => 'not display name',
            ],
            'meta' => [
                'name' => 'Found in meta',
            ],
            'id' => 7,
        ]);

        $this->assertSame('Found in meta', $entity->displayName);
    }

    public function testDotNotationWithArrayAccess(): void {
        $data = new \ArrayObject([
            'attributes' => new \ArrayObject([
                'displayName' => 'From ArrayObject',
            ]),
        ]);

        $entity = DotNotationEntity::from([
            'attributes' => [
                'displayName' => 'From ArrayObject',
            ],
            'id' => 8,
        ]);

        $this->assertSame('From ArrayObject', $entity->displayName);
    }

    public function testDotNotationDisabled(): void {
        // When useDotNotation is false, dots should be treated as literal characters
        $entity = DotNotationEntity::from([
            'simple_name' => 'Without Dot Notation',
            'displayName' => 'Test',
            'id' => 9,
        ]);

        $this->assertSame('Without Dot Notation', $entity->noDotNotation);
    }

    public function testDotNotationNullForMissingPath(): void {
        $entity = DotNotationEntity::from([
            'displayName' => 'Test',
            'id' => 10,
        ]);

        // deepValue should remain null since no path matches
        $this->assertNull($entity->deepValue);
    }

    public function testDotNotationSecondLevelAltName(): void {
        // Test using the second alt name 'config.theme' instead of first
        $entity = DotNotationEntity::from([
            'config' => [
                'theme' => 'light',
            ],
            'displayName' => 'Test User',
            'id' => 11,
        ]);

        $this->assertSame('light', $entity->theme);
    }
}
