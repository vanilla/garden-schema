<?php

namespace Garden\Schema\Tests\Fixtures;

use Garden\Schema\Entity;
use Garden\Schema\PropertySchema;

/**
 * Simple test fixture for nested entity relationships.
 */
class ChildEntity extends Entity {
    
    #[PropertySchema(["minLength" => 1])]
    public string $id;
}
