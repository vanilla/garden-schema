<?php

namespace Garden\Schema\Tests\Fixtures;

use Garden\Schema\Entity;

/**
 * Test fixture for DateTimeImmutable property support.
 */
class DateTimeEntity extends Entity {
    public string $name;
    public \DateTimeImmutable $createdAt;
    public ?\DateTimeImmutable $updatedAt = null;
}
