<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2017 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema\Tests;

use PHPUnit\Framework\TestCase;
use Garden\Schema\Schema;

/**
 * Test property access of the schema object itself.
 */
class PropertyTest extends TestCase {
    /**
     * Test basic property access.
     */
    public function testPropertyAccess() {
        $schema = Schema::parse([]);

        $this->assertEmpty($schema->getDescription());
        $schema->setDescription('foo');
        $this->assertSame('foo', $schema->getDescription());
        $this->assertSame('foo', $schema->jsonSerialize()['description']);
        $this->assertSame('foo', $schema['description']);

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
     * Test **getID** and **setID**.
     */
    public function testGetSetID() {
        $schema = new Schema();

        $this->assertEmpty($schema->getID());
        $schema->setID('test');
        $this->assertEquals('test', $schema->getID());
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

    /**
     * Test deep field getting.
     */
    public function testGetField() {
        $schema = Schema::parse([':a' => 's']);

        $this->assertSame('string', $schema->getField('items.type'));
    }

    /**
     * Test deep field setting.
     */
    public function testSetField() {
        $schema = Schema::parse([':a' => 's']);

        $schema->setField('items.type', 'integer');
        $this->assertSame('integer', $schema->getField('items.type'));
    }

    public function testArrayAccess() {
        $schema = Schema::parse([':a' => 's']);

        $schema['id'] = 'foo';
        $this->assertEquals('foo', $schema['id']);

        unset($schema['id']);
        $this->assertFalse(isset($schema['id']));
    }

    /**
     * Test nested schema deep field access.
     */
    public function testNestedGetSetField() {
        $sc1 = Schema::parse([':a' => 's']);
        $sc2 = Schema::parse(['id:i', 'name:s']);

        $sc1->setField('items', $sc2);

        $this->assertSame('string', $sc1->getField('items.properties.name.type'));

        $sc1->setField('items.properties.name.type', 'integer');
        $this->assertSame('integer', $sc1->getField('items.properties.name.type'));
        $this->assertSame('integer', $sc2->getField('properties.name.type'));
    }
}
