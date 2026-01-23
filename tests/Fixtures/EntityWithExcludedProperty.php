<?php

namespace Garden\Schema\Tests\Fixtures;

use Garden\Schema\Entity;
use Garden\Schema\ExcludeFromSchema;

/**
 * Test fixture for ExcludeFromSchema attribute functionality.
 */
class EntityWithExcludedProperty extends Entity {
    public string $name;

    #[ExcludeFromSchema]
    public string $computed = '';

    #[ExcludeFromSchema]
    public ?array $cache = null;

    public int $count;
}
