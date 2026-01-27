<?php

namespace Garden\Schema\Tests\Fixtures;

use Garden\Schema\Entity;
use Garden\Schema\EntityDefaultInterface;

/**
 * Test fixture for EntityDefaultInterface.
 */
class DefaultMetadataEntity extends Entity implements EntityDefaultInterface {
    public string $version;
    public bool $draft;

    public static function default(): static {
        $instance = new static();
        $instance->version = '1.0';
        $instance->draft = true;
        return $instance;
    }
}
