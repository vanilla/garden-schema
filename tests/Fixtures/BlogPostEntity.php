<?php

namespace Garden\Schema\Tests\Fixtures;

use Garden\Schema\Entity;
use Garden\Schema\MapSubProperties;

/**
 * Test fixture for MapSubProperties attribute functionality.
 */
class BlogPostEntity extends Entity {
    public int $postID;

    public string $title;

    #[MapSubProperties(
        keys: ['authorID', 'authorName'],
        mapping: ['metadata.authorEmail' => 'email', 'metadata.authorBio' => 'bio']
    )]
    public SimpleAuthorEntity $author;
}
