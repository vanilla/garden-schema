<?php

namespace Garden\Schema\Tests\Fixtures;

use Garden\Schema\Entity;

/**
 * Simple test fixture representing an author for MapSubProperties tests.
 */
class SimpleAuthorEntity extends Entity {
    public int $authorID;

    public string $authorName;

    public ?string $email = null;

    public ?string $bio = null;
}
