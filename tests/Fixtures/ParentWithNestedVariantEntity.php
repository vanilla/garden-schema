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
use Garden\Schema\PropertySchema;

/**
 * Test fixture for parent entity with nested variant-aware child.
 */
class ParentWithNestedVariantEntity extends Entity {
    public int $id;

    public string $name;

    /**
     * Parent-level admin-only field.
     */
    #[ExcludeFromVariant(CustomVariant::Public)]
    public string $parentAdminField = '';

    /**
     * Parent-level internal-only field.
     */
    #[IncludeOnlyInVariant(CustomVariant::Internal)]
    public string $parentInternalField = '';

    /**
     * Single nested entity with variant-specific properties.
     */
    public ?NestedVariantChildEntity $child = null;

    /**
     * Array of nested entities with variant-specific properties.
     */
    #[PropertySchema(['items' => ['entityClassName' => NestedVariantChildEntity::class]])]
    public array $children = [];
}
