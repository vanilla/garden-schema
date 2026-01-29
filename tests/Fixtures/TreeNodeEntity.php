<?php

namespace Garden\Schema\Tests\Fixtures;

use Garden\Schema\Entity;

/**
 * Self-referencing entity for testing recursive structures.
 */
class TreeNodeEntity extends Entity {
    public string $value;
    public ?TreeNodeEntity $child = null;
}
