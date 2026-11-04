<?php

namespace Garden\Schema\Tests;

use Garden\Schema\Entity;
use Garden\Schema\Schema;
use Garden\Schema\Tests\Fixtures\DateTimeEntity;
use PHPUnit\Framework\TestCase;

/**
 * A datetime that encodes itself as an RFC3339 string.
 */
class SerializingDate extends \DateTimeImmutable implements \JsonSerializable {
    /**
     * @return string
     */
    public function jsonSerialize(): string {
        return $this->format(\DateTimeInterface::RFC3339);
    }
}

/**
 * A date subclass distinct from the configured schema date class.
 */
class SpecificDate extends \DateTimeImmutable {
}

/**
 * An entity whose date property names a specific subclass.
 */
class SpecificDateEntity extends Entity {
    public SpecificDate $createdAt;
}

/**
 * Tests for {@link Schema::setDateClass()}.
 */
class DateClassTest extends TestCase {
    /**
     * Restore the default date class.
     */
    protected function tearDown(): void {
        Schema::setDateClass(\DateTimeImmutable::class);
    }

    /**
     * The default class is DateTimeImmutable until a caller replaces it.
     */
    public function testDefaultDateClass(): void {
        $valid = Schema::parse([':dt'])->validate('2024-01-15T10:30:00+00:00');

        $this->assertInstanceOf(\DateTimeImmutable::class, $valid);
        $this->assertSame(\DateTimeImmutable::class, $valid::class);
    }

    /**
     * Schema datetime fields and DateTimeImmutable entity properties use the configured class.
     */
    public function testConfiguredDateClass(): void {
        Schema::setDateClass(SerializingDate::class);

        $valid = Schema::parse([':dt'])->validate('2024-01-15T10:30:00+00:00');
        $entity = DateTimeEntity::from([
            'name' => 'test',
            'createdAt' => '2024-01-15T10:30:00+00:00',
        ]);

        $this->assertInstanceOf(SerializingDate::class, $valid);
        $this->assertInstanceOf(SerializingDate::class, $entity->createdAt);
        $this->assertSame('2024-01-15T10:30:00+00:00', $valid->jsonSerialize());
    }

    /**
     * A property typed as its own subclass is not replaced by the configured class.
     */
    public function testSpecificSubclassIsKept(): void {
        Schema::setDateClass(SerializingDate::class);
        $entity = SpecificDateEntity::from([
            'createdAt' => '2024-01-15T10:30:00+00:00',
        ]);

        $this->assertInstanceOf(SpecificDate::class, $entity->createdAt);
        $this->assertNotInstanceOf(SerializingDate::class, $entity->createdAt);
    }

    /**
     * A class that does not extend DateTimeImmutable is rejected.
     */
    public function testInvalidDateClass(): void {
        $this->expectException(\InvalidArgumentException::class);
        Schema::setDateClass(\DateTime::class);
    }
}
