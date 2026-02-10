<?php

namespace Garden\Schema\Tests\Fixtures;

use Garden\Schema\Entity;
use Garden\Schema\PropertySchema;

/**
 * Test fixture for schema-only properties (private/protected with PropertySchema attribute).
 */
class SchemaOnlyPropertyEntity extends Entity {
    public string $name;

    public int $count;

    /** Private property without PropertySchema - should NOT be in schema */
    private string $secret = 'hidden';

    /** Protected property without PropertySchema - should NOT be in schema */
    protected int $internal = 42;

    /** Private property WITH PropertySchema - should be in schema only (not encoded/decoded) */
    #[PropertySchema(['type' => 'string', 'description' => 'A schema-only private field'])]
    private string $schemaOnlyPrivate = 'private-default';

    /** Protected property WITH PropertySchema - should be in schema only (not encoded/decoded) */
    #[PropertySchema(['type' => 'integer', 'minimum' => 0, 'description' => 'A schema-only protected field'])]
    protected int $schemaOnlyProtected = 100;
}
