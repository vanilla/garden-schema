<?php

namespace Garden\Schema\Tests\Fixtures;

use Garden\Schema\Entity;
use Garden\Schema\PropertySchema;

/**
 * Test fixture demonstrating various entity property types and configurations.
 */
class BasicEntity extends Entity {
    private string $secret = 'hidden';
    protected int $internal = 42;

    public string $name;
    public int $count;
    public float $ratio;
    public array $tags;

    #[PropertySchema(['items' => ['type' => 'string']])]
    public array $labels;

    #[PropertySchema(['type' => 'array', 'items' => ['type' => 'string'], 'minItems' => 1])]
    public array $names;

    public ?string $note;
    public string $withDefault = 'x';
    public $raw;
    public TestEnum $status;
    public ChildEntity $child;
    public ?ChildEntity $maybeChild;
}
