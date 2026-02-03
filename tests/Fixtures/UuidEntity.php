<?php

namespace Garden\Schema\Tests\Fixtures;

use Garden\Schema\Entity;
use Ramsey\Uuid\UuidInterface;

/**
 * Test fixture for UuidInterface property support.
 */
class UuidEntity extends Entity {
    public string $name;
    public UuidInterface $id;
    public ?UuidInterface $parentId = null;
}
