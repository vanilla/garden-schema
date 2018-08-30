<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema\Tests;

use PHPUnit\Framework\TestCase;
use Garden\Schema\Validation;
use Garden\Schema\Tests\Fixtures\TestValidation;

/**
 * Test the {@link Validation}.
 */
class ValidationClassTest extends TestCase {
    /**
     * Adding an error to the validation object should make the object not valid.
     */
    public function testAddErrorNotValid() {
        $vld = new Validation();

        $vld->addError('foo', 'error');
        $this->assertFalse($vld->isValid());
    }

    /**
     * Test adding an error with a path.
     */
    public function testAddErrorWithPath() {
        $vld = new Validation();

        $vld->addError('foo/bar', 'error');
        $error = $vld->getErrors()[0];

        $this->assertArraySubset(
            ['field' => 'foo/bar', 'error' => 'error'],
            $error
        );
    }

    /**
     * Errors with "{field}" codes should be replaced.
     */
    public function testMessageReplacements() {
        $vld = new Validation();
        $vld->addError('foo', 'The {field}!');

        $this->assertSame('foo: The foo!', $vld->getMessage());
    }

    /**
     * The status should be the max status.
     */
    public function testCalcStatus() {
        $vld = new Validation();

        $vld->addError('foo', 'err', ['number' => 302])
            ->addError('bar', 'err', ['number' => 301]);

        $this->assertSame(302, $vld->getNumber());
    }

    /**
     * If there is no status and an error then the status should be 400.
     */
    public function testDefaultStatus() {
        $vld = new Validation();

        $vld->addError('foo', 'err');

        $this->assertSame(400, $vld->getNumber());
    }

    /**
     * A valid object should have a 200 status.
     */
    public function testValidStatus() {
        $vld = new Validation();
        $this->assertSame(200, $vld->getNumber());
    }

    /**
     * The main status should override any sub-statuses.
     */
    public function testMainStatusOverride() {
        $vld = new Validation();

        $vld->addError('foo', 'bar')
            ->setMainNumber(100);

        $this->assertSame(100, $vld->getNumber());
    }

    /**
     * Messages should be translated.
     */
    public function testMessageTranslation() {
        $vld = new TestValidation();
        $vld->setTranslateFieldNames(true);

        $vld->addError('it', 'Keeping {field} {number}', ['number' => 100]);

        $this->assertSame('!!it: !Keeping !it 100', $vld->getFullMessage());
    }

    /**
     * Test message plural formatting.
     */
    public function testPlural() {
        $vld = new TestValidation();
        $vld->addError('', '{a,plural, apple} {b,plural,berry,berries} {b, plural, pear}.', ['a' => 1, 'b' => 2]);
        $this->assertSame('!apple berries pears.', $vld->getMessage());
    }

    /**
     * Messages that start with "@" should not be translated.
     */
    public function testNoTranslate() {
        $vld = new TestValidation();
        $this->assertSame('foo', $vld->parentTranslate('@foo'));
    }

    /**
     * The error code cannot be empty.
     *
     * @expectedException \InvalidArgumentException
     */
    public function testEmptyError() {
        $vld = new Validation();
        $vld->addError('foo', '');
    }

    /**
     * The error count function should return the correct count when filtered by field.
     */
    public function testErrorCountWithField() {
        $vld = new Validation();
        $vld->addError('foo', 'foo');
        $vld->addError('bar', 'foo');

        $this->assertSame(1, $vld->getErrorCount('foo'));
        $this->assertSame(2, $vld->getErrorCount());
    }

    /**
     * Null and empty strings have a different meaning in `Validation::getErrorCount()`.
     */
    public function testErrorCountNullVsEmpty() {
        $vld = new Validation();
        $vld->addError('', 'foo');
        $vld->addError('foo', 'foo');

        $this->assertSame(1, $vld->getErrorCount(''));
        $this->assertSame(2, $vld->getErrorCount());
    }
}
