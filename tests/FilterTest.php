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

/**
 * Tests for schema filters.
 */
class FilterTest extends TestCase {
    /**
     * A validating filter can make an invalid value, valid.
     */
    public function testValidatingFilterValid() {
        $sch = new Schema([
            'type' => 'array'
        ]);
        $sch->addFilter('', function ($v) {
            return (int)$v;
        }, true);

        $this->assertSame(123, $sch->validate('123'));
    }

    /**
     * A validating filter can make a valid value invalid.
     */
    public function testValidatingFilterInvalid() {
        $sch = new Schema([
            'type' => 'array'
        ]);
        $sch->addFilter('', function ($v) {
            return Invalid::value();
        }, true);

        $this->assertFalse($sch->isValid([1, 2, 3]));
    }

    /**
     * A format filter applies to a field's format rather than its path.
     */
    public function testFormatFilter() {
        $sch = new Schema([
            'type' => 'integer',
            'format' => 'foo',
        ]);
        $sch->addFormatFilter('foo', function ($v) {
            return 123;
        });

        $this->assertSame(123, $sch->validate(456));
    }

    /**
     * A validating format filter can override the default format behaviour.
     */
    public function testFormatFilterOverride() {
        $sch = new Schema([
            'type' => 'string',
            'format' => 'date-time',
        ]);
        $sch->addFormatFilter('date-time', function ($v) {
            $dt = new \DateTime($v);
            return $dt->format(\DateTime::RFC3339);
        }, true);

        $this->assertSame('2018-03-26T00:00:00+00:00', $sch->validate('March 26 2018 UTC'));
    }
}
