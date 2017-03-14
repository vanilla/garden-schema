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
        $schema = Schema::parse([]);

        $this->assertEmpty($schema->getDescription());
        $schema->setDescription('foo');
        $this->assertSame('foo', $schema->getDescription());
        $this->assertSame('foo', $schema->jsonSerialize()['description']);

        $this->assertSame(0, $schema->getFlags());
        $behaviors = [
            Schema::VALIDATE_EXTRA_PROPERTY_NOTICE,
            Schema::VALIDATE_EXTRA_PROPERTY_EXCEPTION
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

        $schema->setFlag(Schema::VALIDATE_EXTRA_PROPERTY_NOTICE, true);
        $this->assertTrue($schema->hasFlag(Schema::VALIDATE_EXTRA_PROPERTY_NOTICE));

        $schema->setFlag(Schema::VALIDATE_EXTRA_PROPERTY_EXCEPTION, true);
        $this->assertTrue($schema->hasFlag(Schema::VALIDATE_EXTRA_PROPERTY_EXCEPTION));
        $this->assertTrue($schema->hasFlag(Schema::VALIDATE_EXTRA_PROPERTY_NOTICE));

        $schema->setFlag(Schema::VALIDATE_EXTRA_PROPERTY_NOTICE, false);
        $this->assertFalse($schema->hasFlag(Schema::VALIDATE_EXTRA_PROPERTY_NOTICE));
        $this->assertTrue($schema->hasFlag(Schema::VALIDATE_EXTRA_PROPERTY_EXCEPTION));
    }

    /**
     * Description must be a string.
     *
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidDescription() {
        $schema = Schema::parse([]);
        $schema->setDescription(123);
    }

    /**
     * The validation behavior should be an appropriate constant.
     *
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidBehavior() {
        $schema = Schema::parse([]);
        $schema->setFlags('foo');
    }
}
