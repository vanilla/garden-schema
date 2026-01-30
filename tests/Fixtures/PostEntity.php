<?php
/**
 * @author Adam Charron <adam@vanillaforums.com>
 * @copyright 2009-2024 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema\Tests\Fixtures;

use Garden\Schema\Entity;
use Garden\Schema\ExcludeFromVariant;
use Garden\Schema\NestedVariant;
use Garden\Schema\PropertySchema;
use Garden\Schema\SchemaVariant;

/**
 * Test fixture for a blog post entity demonstrating #[NestedVariant] usage.
 *
 * The author property is always serialized as Fragment, even when the post is Full.
 */
class PostEntity extends Entity {
    public int $id;

    public string $title;

    /**
     * Full content - excluded from Fragment variant.
     */
    #[ExcludeFromVariant(SchemaVariant::Fragment)]
    public string $content = '';

    /**
     * Author is always serialized as Fragment, regardless of post variant.
     * This prevents deeply nested full author data in post responses.
     */
    #[NestedVariant(SchemaVariant::Fragment)]
    public ?AuthorEntity $author = null;

    /**
     * Array of comment authors, also serialized as Fragment.
     */
    #[NestedVariant(SchemaVariant::Fragment)]
    #[PropertySchema(['items' => ['entityClassName' => AuthorEntity::class]])]
    public array $commentAuthors = [];
}
