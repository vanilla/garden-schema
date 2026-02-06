<?php

namespace Garden\Schema\Tests\Fixtures;

use Garden\Schema\Entity;
use Garden\Schema\SchemaOrder;

/**
 * Test fixture for SchemaOrder attribute functionality.
 *
 * Without ordering, properties would appear in declaration order:
 * description, id, title, name, tags
 *
 * With SchemaOrder, the expected order is:
 * id (order 1), name (order 2), title (order 3), description, tags
 */
class SchemaOrderEntity extends Entity {
    public string $description;

    #[SchemaOrder(1)]
    public int $id;

    #[SchemaOrder(3)]
    public string $title;

    #[SchemaOrder(2)]
    public string $name;

    public array $tags;
}
