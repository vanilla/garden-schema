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
use Garden\Schema\SchemaVariant;

/**
 * Test fixture demonstrating schema variant attributes.
 */
class ArticleEntity extends Entity {
    /**
     * Unique identifier - not mutable by users.
     */
    #[ExcludeFromVariant(SchemaVariant::Mutable)]
    public int $id;

    /**
     * Article title - included in all variants.
     */
    public string $title;

    /**
     * URL slug - included in all variants.
     */
    public string $slug;

    /**
     * Full article body - excluded from fragment (too large for lists).
     */
    #[ExcludeFromVariant(SchemaVariant::Fragment)]
    public string $body;

    /**
     * Article excerpt - excluded from fragment.
     */
    #[ExcludeFromVariant(SchemaVariant::Fragment)]
    public ?string $excerpt;

    /**
     * Creation timestamp - not mutable by users.
     */
    #[ExcludeFromVariant(SchemaVariant::Mutable)]
    public \DateTimeImmutable $createdAt;

    /**
     * Last update timestamp - not mutable by users.
     */
    #[ExcludeFromVariant(SchemaVariant::Mutable)]
    public \DateTimeImmutable $updatedAt;

    /**
     * Author ID - can only be set on creation.
     */
    #[ExcludeFromVariant(SchemaVariant::Mutable)]
    public int $authorId;

    /**
     * Initial password for protected articles - only valid on creation.
     */
    #[IncludeOnlyInVariant(SchemaVariant::Create)]
    public ?string $initialPassword;

    /**
     * Invitation code - only valid on creation but also in full for display.
     */
    #[IncludeOnlyInVariant(SchemaVariant::Create, SchemaVariant::Full)]
    public ?string $inviteCode;

    /**
     * Tags - mutable and included in all standard variants.
     */
    #[PropertySchema(['items' => ['type' => 'string']])]
    public array $tags = [];
}
