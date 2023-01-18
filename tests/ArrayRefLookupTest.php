<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema\Tests;

use Garden\Schema\ArrayRefLookup;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the `ArrayRefLookup` class.
 */
class ArrayRefLookupTest extends TestCase {
    /**
     * @var ArrayRefLookup
     */
    private $lookup;

    /**
     * Create test object for each test.
     */
    public function setUp(): void {
        parent::setUp();

        $this->lookup = new ArrayRefLookup([
            'foo' => [
                'bar' => new \ArrayObject([
                    '~/baz' => 123,
                ]),
            ],
        ]);
    }

    /**
     * Test basic getter/setter behavior.
     */
    public function testArrayGetterSetter() {
        $arr = new ArrayRefLookup(['foo' => 'bar']);
        $this->assertEquals(['foo' => 'bar'], $arr->getArray());

        $arr->setArray(['foo' => 'baz']);
        $this->assertEquals(['foo' => 'baz'], $arr->getArray());
    }

    /**
     * Lookup a reference on the test lookup object.
     *
     * @param string $ref The reference to find.
     * @return mixed|null Returns the resolved reference.
     */
    protected function lookupRef(string $ref) {
        return $this->lookup->__invoke($ref);
    }

    /**
     * The root reference should return the entire array.s
     */
    public function testRootLookup() {
        $this->assertRefLookup('#/', $this->lookup->getArray());
    }

    /**
     * Test a lookup one array deep.
     */
    public function testFolder() {
        $this->assertRefLookup('#/foo', $this->lookup->getArray()['foo']);
    }

    /**
     * Test a lookup one level deep.
     */
    public function testPath() {
        $this->assertRefLookup('#/foo/bar', $this->lookup->getArray()['foo']['bar']);
    }

    /**
     * Test a lookup on a path with special characters.
     */
    public function testEscapedPath() {
        $this->assertRefLookup('#/foo/bar/~0~1baz', $this->lookup->getArray()['foo']['bar']['~/baz']);
    }

    /**
     * Test a lookup where the key doesn't exist.
     */
    public function testMissingKey() {
        $this->assertRefLookup('#/not', null);
    }

    /**
     * Test a lookup where the parent key exists, but the child doesn't.
     */
    public function testPartialMissingKey() {
        $this->assertRefLookup('#/foo/not', null);
    }

    /**
     * The array lookup does not support hosts.
     */
    public function testHostLookup() {
        $this->expectException(\InvalidArgumentException::class);
        $this->assertRefLookup('http://example.com#/foo', null);
    }

    /**
     * The array lookup does not support paths.
     */
    public function testPathLookup() {
        $this->expectException(\InvalidArgumentException::class);
        $this->assertRefLookup('/foo#/foo', null);
    }

    /**
     * The array lookup does not support relative references.
     */
    public function testRelativeReference() {
        $this->expectException(\InvalidArgumentException::class);
        $this->assertRefLookup('#foo', null);
    }

    /**
     * The array lookup does not support relative references.
     */
    public function testEmptyReference() {
        $this->expectException(\InvalidArgumentException::class);
        $this->assertRefLookup('#', null);
    }

    /**
     * Assert the value of a reference lookup./
     *
     * @param string $ref The reference to lookup.
     * @param mixed $expected The expected lookup result.
     */
    public function assertRefLookup(string $ref, $expected) {
        $val = $this->lookupRef($ref);
        if ($expected === null) {
            $this->assertNull($val);
        } else {
            $this->assertEquals($expected, $val);
        }
    }
}
