<?php
/**
 * @author Adam Charron <adam@vanillaforums.com>
 * @copyright 2009-2024 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema\Tests\Fixtures;

/**
 * Custom variant enum for testing generic variant support.
 */
enum CustomVariant: string {
    case Public = 'public';
    case Admin = 'admin';
    case Internal = 'internal';
}
