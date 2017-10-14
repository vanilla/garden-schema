<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2017 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema\Tests;

use Garden\Schema\Schema;

class ArrayValidationTest extends AbstractSchemaTest {
    public function testMinItems() {
        $sch = new Schema(['type' => 'array', 'minItems' => 1]);

        $this->assertTrue($sch->isValid([1]));
        $this->assertTrue($sch->isValid([1, 2]));
        $this->assertFalse($sch->isValid([]));
    }

    public function testMaxItems() {
        $sch = new Schema(['type' => 'array', 'maxItems' => 2]);

        $this->assertTrue($sch->isValid([1]));
        $this->assertTrue($sch->isValid([1, 2]));
        $this->assertFalse($sch->isValid([1, 2, 3]));
    }
}
