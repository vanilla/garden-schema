<?php

namespace Garden\Schema\Tests\Fixtures;

use Garden\Schema\Entity;
use Garden\Schema\PropertyAltNames;

/**
 * Test fixture for PropertyAltNames dot notation functionality.
 */
class DotNotationEntity extends Entity {
    #[PropertyAltNames(['attributes.displayName', 'meta.name', 'name'], primaryAltName: 'attributes.displayName')]
    public string $displayName;

    #[PropertyAltNames(['settings.preferences.theme', 'config.theme'], primaryAltName: 'settings.preferences.theme')]
    public ?string $theme = null;

    // Single alt name - primaryAltName is inferred
    #[PropertyAltNames(['deeply.nested.value.here'])]
    public ?string $deepValue = null;

    // Single alt name with useDotNotation: false - primaryAltName is inferred
    #[PropertyAltNames(['simple_name'], useDotNotation: false)]
    public ?string $noDotNotation = null;

    public int $id;
}
