<?php

namespace Garden\Schema\Tests\Fixtures;

use Garden\Schema\Entity;

/**
 * Test fixture for entity with integer-backed enum property.
 */
class IntEnumEntity extends Entity {
    public string $name;
    public IntEnum $priority;
}
