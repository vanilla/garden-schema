<?php

namespace Garden\Schema\Tests\Fixtures;

use Garden\Schema\Entity;
use Garden\Schema\PropertySchema;

/**
 * Test fixture for arrays of nested entities via PropertySchema.
 */
class EntityWithChildrenArray extends Entity {
    public string $name;

    #[PropertySchema(['items' => ['entityClassName' => ChildEntity::class]])]
    public array $children;
}
