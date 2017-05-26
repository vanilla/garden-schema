<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2017 Vanilla Forums Inc.
 * @license Proprietary
 */

namespace Garden\Schema\Tests;

use Garden\Schema\Schema;
use PHPUnit\Framework\TestCase;

class RealWorldTest extends TestCase {
    /**
     * An optional field should be stripped when provided with an empty string.
     */
    public function testEmptyOptional() {
        $sch = Schema::parse(['a:i?']);

        $valid = $sch->validate(['a' => '']);
        $this->assertSame([], $valid);
    }

    /**
     * An optional field should be stripped when provided with null.
     */
    public function testNullOptional() {
        $sch = Schema::parse(['a:i?']);

        $valid = $sch->validate(['a' => null]);
        $this->assertSame([], $valid);
    }

    /**
     * A nullable field should convert an empty string to null.
     */
    public function testEmptyNullable() {
        $sch = Schema::parse(['a:i|n']);

        $valid = $sch->validate(['a' => '']);
        $this->assertSame(['a' => null], $valid);
    }

    /**
     * A nullable optional field should convert various values to null.
     */
    public function testNullableOptional() {
        $sch = Schema::parse(['a:i|n?']);

        $valid = $sch->validate(['a' => '']);
        $this->assertSame(['a' => null], $valid);

        $valid = $sch->validate(['a' => null]);
        $this->assertSame(['a' => null], $valid);
    }

    /**
     * An optional string field should not strip empty strings.
     */
    public function testOptionalEmptyString() {
        $sch = Schema::parse(['a:s?']);

        $valid = $sch->validate(['a' => '']);
        $this->assertSame(['a' => ''], $valid);
    }
}
