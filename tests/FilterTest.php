<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema\Tests;

use Garden\Schema\Invalid;
use Garden\Schema\Schema;
use PHPUnit\Framework\TestCase;

class FilterTest extends TestCase {
    public function testValidatingFilterValid() {
        $sch = new Schema([
            'type' => 'array'
        ]);
        $sch->addFilter('', function ($v) {
            return (int)$v;
        }, true);

        $this->assertSame(123, $sch->validate('123'));
    }

    public function testValidatingFilterInvalid() {
        $sch = new Schema([
            'type' => 'array'
        ]);
        $sch->addFilter('', function ($v) {
            return Invalid::value();
        }, true);

        $this->assertFalse($sch->isValid([1, 2, 3]));

    }
}
