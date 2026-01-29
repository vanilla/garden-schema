<?php

namespace Garden\Schema\Tests\Fixtures;

use Garden\Schema\Entity;

/**
 * Test fixture for entities with a child that implements EntityDefaultInterface.
 */
class EntityWithDefaultChild extends Entity {
    public string $title;
    public DefaultMetadataEntity $metadata;
    public ?DefaultMetadataEntity $optionalMetadata;
}
