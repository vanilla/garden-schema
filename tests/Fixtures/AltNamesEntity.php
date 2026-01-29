<?php

namespace Garden\Schema\Tests\Fixtures;

use Garden\Schema\Entity;
use Garden\Schema\PropertyAltNames;

/**
 * Test fixture for PropertyAltNames attribute functionality.
 */
class AltNamesEntity extends Entity {
    #[PropertyAltNames('user_name', 'userName', 'uname')]
    public string $name;

    #[PropertyAltNames('e-mail', 'emailAddress')]
    public ?string $email = null;

    public int $count;
}
