<?php
/**
 * @author Adam Charron <adam@vanillaforums.com>
 * @copyright 2009-2024 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema\Tests\Fixtures;

use Garden\Schema\Entity;
use Garden\Schema\ExcludeFromVariant;
use Garden\Schema\IncludeOnlyInVariant;

/**
 * Test fixture demonstrating custom variant enum support.
 */
class CustomVariantEntity extends Entity {
    public int $id;

    public string $name;

    /**
     * Only visible to admins and internal.
     */
    #[ExcludeFromVariant(CustomVariant::Public)]
    public string $adminNotes = '';

    /**
     * Only visible internally.
     */
    #[IncludeOnlyInVariant(CustomVariant::Internal)]
    public string $internalSecret = '';

    /**
     * Nested entity for testing recursive serialization.
     */
    public ?ChildEntity $child = null;
}
