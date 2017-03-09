<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2017 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema\Tests;

use Garden\Schema\Schema;

class PropertyTest extends \PHPUnit_Framework_TestCase {
    /**
     * Test basic property access.
     */
    public function testPropertyAccess() {
        $schema = new Schema([]);

        $this->assertEmpty($schema->getDescription());
        $schema->setDescription('foo');
        $this->assertSame('foo', $schema->getDescription());
        $this->assertSame('foo', $schema->jsonSerialize()['description']);

        $this->assertSame(0, $schema->getFlags());
        $behaviors = [
            Schema::FLAG_EXTRA_PROPERTIES_NOTICE,
            Schema::FLAG_EXTRA_PROPERTIES_EXCEPTION
        ];
        foreach ($behaviors as $behavior) {
            $schema->setFlags($behavior);
            $this->assertSame($behavior, $schema->getFlags());
        }
    }

    /**
     * Test flag getters and setters.
     */
    public function testGetSetFlag() {
        $schema = new Schema();

        $schema->setFlag(Schema::FLAG_EXTRA_PROPERTIES_NOTICE, true);
        $this->assertTrue($schema->hasFlag(Schema::FLAG_EXTRA_PROPERTIES_NOTICE));

        $schema->setFlag(Schema::FLAG_EXTRA_PROPERTIES_EXCEPTION, true);
        $this->assertTrue($schema->hasFlag(Schema::FLAG_EXTRA_PROPERTIES_EXCEPTION));
        $this->assertTrue($schema->hasFlag(Schema::FLAG_EXTRA_PROPERTIES_NOTICE));

        $schema->setFlag(Schema::FLAG_EXTRA_PROPERTIES_NOTICE, false);
        $this->assertFalse($schema->hasFlag(Schema::FLAG_EXTRA_PROPERTIES_NOTICE));
        $this->assertTrue($schema->hasFlag(Schema::FLAG_EXTRA_PROPERTIES_EXCEPTION));
    }

    /**
     * Description must be a string.
     *
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidDescription() {
        $schema = new Schema([]);
        $schema->setDescription(123);
    }

    /**
     * The validation behavior should be an appropriate constant.
     *
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidBehavior() {
        $schema = new Schema([]);
        $schema->setFlags('foo');
    }
}
