<?php
/**
 * @author Adam Charron <adam@vanillaforums.com>
 * @copyright 2009-2024 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema\Tests\Fixtures;

use Garden\Schema\Entity;
use Garden\Schema\ExcludeFromVariant;
use Garden\Schema\SchemaVariant;

/**
 * Test fixture demonstrating multiple ExcludeFromVariant attributes.
 */
class MultiExcludeEntity extends Entity {
    public int $id;

    public string $name;

    /**
     * Excluded from both Fragment and Mutable using multiple attributes.
     */
    #[ExcludeFromVariant(SchemaVariant::Fragment)]
    #[ExcludeFromVariant(SchemaVariant::Mutable)]
    public string $sensitiveData;

    /**
     * Excluded from multiple variants using single attribute with multiple values.
     */
    #[ExcludeFromVariant(SchemaVariant::Fragment, SchemaVariant::Mutable, SchemaVariant::Create)]
    public string $internalOnly;
}
