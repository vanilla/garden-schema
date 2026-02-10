<?php

namespace Garden\Schema\Tests\Fixtures;

use Garden\Schema\Entity;
use Garden\Schema\PropertyAltNames;

/**
 * Test fixture for PropertyAltNames attribute functionality.
 */
class AltNamesEntity extends Entity {
    #[PropertyAltNames(['user_name', 'userName', 'uname'], primaryAltName: 'user_name')]
    public string $name;

    #[PropertyAltNames(['e-mail', 'emailAddress'], primaryAltName: 'e-mail')]
    public ?string $email = null;

    // Single string syntax example - primaryAltName is inferred
    #[PropertyAltNames('nick_name')]
    public ?string $nickname = null;

    public int $count;
}
