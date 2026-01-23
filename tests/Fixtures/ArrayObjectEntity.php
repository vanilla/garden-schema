<?php

namespace Garden\Schema\Tests\Fixtures;

use Garden\Schema\Entity;

/**
 * Test fixture for ArrayObject property support.
 */
class ArrayObjectEntity extends Entity {
    public string $name;
    public \ArrayObject $data;
    public ?\ArrayObject $optionalData = null;
}
