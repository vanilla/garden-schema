<?php

namespace Garden\Schema\Tests\Fixtures;

use Garden\Schema\SchemaOrder;

class ReflectionUtilsSchemaChildEntity extends ReflectionUtilsSchemaParentEntity {
    public string $childA;

    #[SchemaOrder(1)]
    public string $schemaOrdered;
}
