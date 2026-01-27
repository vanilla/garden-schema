<?php

namespace Garden\Schema\Tests;

use Garden\Schema\Tests\Fixtures\AltNamesEntity;
use PHPUnit\Framework\TestCase;

/**
 * Tests for PropertyAltNames attribute on Entity properties.
 */
class EntityPropertyAltNamesTest extends TestCase {
    public function testPropertyAltNamesUsesFirstAltName(): void {
        $entity = AltNamesEntity::from([
            'user_name' => 'John',
            'count' => 5,
        ]);

        $this->assertSame('John', $entity->name);
    }

    public function testPropertyAltNamesUsesSecondAltName(): void {
        $entity = AltNamesEntity::from([
            'userName' => 'Jane',
            'count' => 10,
        ]);

        $this->assertSame('Jane', $entity->name);
    }

    public function testPropertyAltNamesPrefersMainName(): void {
        $entity = AltNamesEntity::from([
            'name' => 'MainName',
            'user_name' => 'AltName',
            'count' => 20,
        ]);

        $this->assertSame('MainName', $entity->name);
    }

    public function testPropertyAltNamesMultipleProperties(): void {
        $entity = AltNamesEntity::from([
            'userName' => 'Alice',
            'e-mail' => 'alice@example.com',
            'count' => 25,
        ]);

        $this->assertSame('Alice', $entity->name);
        $this->assertSame('alice@example.com', $entity->email);
    }

    public function testPropertyAltNamesFirstMatchWins(): void {
        // When multiple alt names are present, the first defined alt name wins
        $entity = AltNamesEntity::from([
            'uname' => 'Third',
            'userName' => 'Second',
            'user_name' => 'First',
            'count' => 30,
        ]);

        // user_name is first in the attribute, so it should be used
        $this->assertSame('First', $entity->name);
    }
}
