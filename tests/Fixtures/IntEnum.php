<?php

namespace Garden\Schema\Tests\Fixtures;

/**
 * Test fixture for integer-backed enum support.
 */
enum IntEnum: int {
    case One = 1;
    case Two = 2;
    case Three = 3;
}
