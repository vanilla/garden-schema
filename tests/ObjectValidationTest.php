<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema\Tests;

use Garden\Schema\Schema;

class ObjectValidationTest extends AbstractSchemaTest {
    /**
     * Test the maxProperties property.
     */
    public function testMaxProperties() {
        $sch = new Schema([
            'type' => 'object',
            'maxProperties' => 1
        ]);

        $this->assertTrue($sch->isValid(['a' => 1]));
        $this->assertFalse($sch->isValid(['a' => 1, 'b' => 2]));
    }

    /**
     * Test the minProperties property.
     */
    public function testMinProperties() {
        $sch = new Schema([
            'type' => 'object',
            'minProperties' => 2
        ]);

        $this->assertTrue($sch->isValid(['a' => 1, 'b' => 2]));
        $this->assertFalse($sch->isValid(['a' => 1]));
    }
}
