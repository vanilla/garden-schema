<?php

namespace Garden\Schema\Tests\Fixtures;

use Garden\Schema\Entity;
use Garden\Schema\PropertyAltNames;

/**
 * Test fixture for a conflicted entity.
 *
 * This entity has a property with a primary alt name of "user_name" and another property with the canonical name "user_name".
 * This is a conflict because the primary alt name should be unique.
 */
class ConflictedEntity extends Entity {
    #[PropertyAltNames("user_name")]
    public string $name;

    public string $user_name;
}
