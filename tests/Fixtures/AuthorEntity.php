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
 * Test fixture representing an author entity with variant-specific fields.
 *
 * Used to test #[NestedVariant] attribute functionality.
 */
class AuthorEntity extends Entity {
    public int $id;

    public string $name;

    public string $email;

    /**
     * Full bio - excluded from Fragment variant.
     */
    #[ExcludeFromVariant(SchemaVariant::Fragment)]
    public string $bio = '';

    /**
     * Avatar URL - included in all variants.
     */
    public string $avatarUrl = '';
}
