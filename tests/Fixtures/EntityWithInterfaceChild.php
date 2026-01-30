<?php
/**
 * @author Adam Charron <adam@vanillaforums.com>
 * @copyright 2009-2024 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema\Tests\Fixtures;

use Garden\Schema\Entity;
use Garden\Schema\EntityInterface;

/**
 * Test fixture for an entity with a property typed as EntityInterface.
 */
class EntityWithInterfaceChild extends Entity {
    public string $name;

    /**
     * Child entity typed as EntityInterface instead of Entity.
     */
    public ?EntityInterface $child = null;
}
