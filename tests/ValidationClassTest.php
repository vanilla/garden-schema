<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2017 Vanilla Forums Inc.
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

        $vld->addError('foo.bar', 'error');
        $error = $vld->getErrors()[0];

        $this->assertArraySubset(
            ['field' => 'bar', 'path' => 'foo', 'code' => 'error'],
            $error
        );
    }

    /**
     * Test adding an error with a path and index.
     */
    public function testAddErrorWithPathAndIndex() {
        $vld = new Validation();

        $vld->addError('foo.bar.baz[0]', 'error');
        $error = $vld->getErrors()[0];

        $this->assertArraySubset(
            ['field' => 'baz', 'path' => 'foo.bar', 'index' => 0, 'code' => 'error'],
            $error
        );
    }

    /**
     * Test a calculated error message.
     */
    public function testCalcMessage() {
        $vld = new Validation();

        $vld->addError('foo', 'baz')
            ->addError('foo', 'bar');

        $msg = $vld->getMessage();
        $this->assertSame('baz. bar.', $msg);
    }

    /**
     * A specified main message should be the message.
     */
    public function testMainMessage() {
        $vld = new Validation();
        $vld->setMainMessage('foo')
            ->addError('foo', 'baz');

        $this->assertSame('foo', $vld->getMessage());
    }

    /**
     * Errors with "{field}" codes should be replaced.
     */
    public function testMessageReplacements() {
        $vld = new Validation();
        $vld->addError('foo', 'The {field}!');

        $this->assertSame('The foo!', $vld->getMessage());
    }

    /**
     * The status should be the max status.
     */
    public function testCalcStatus() {
        $vld = new Validation();

        $vld->addError('foo', 'err', 302)
            ->addError('bar', 'err', 301);

        $this->assertSame(302, $vld->getStatus());
    }

    /**
     * If there is no status and an error then the status should be 400.
     */
    public function testDefaultStatus() {
        $vld = new Validation();

        $vld->addError('foo', 'err');

        $this->assertSame(400, $vld->getStatus());
    }

    /**
     * A valid object should have a 200 status.
     */
    public function testValidStatus() {
        $vld = new Validation();
        $this->assertSame(200, $vld->getStatus());
    }

    /**
     * The main status should override any sub-statuses.
     */
    public function testMainStatusOverride() {
        $vld = new Validation();

        $vld->addError('foo', 'bar', 500)
            ->setMainStatus(100);

        $this->assertSame(100, $vld->getStatus());
    }

    /**
     * Messages should be translated.
     */
    public function testMessageTranslation() {
        $vld = new TestValidation();
        $vld->setTranslateFieldNames(true);

        $vld->addError('it', 'Keeping {field} {status}', 100);

        $this->assertSame('!!Keeping !it 100.', $vld->getMessage());
    }

    /**
     * Test message plural formatting.
     */
    public function testPlural() {
        $vld = new TestValidation();
        $vld->addError('it', '{a,plural, apple} {b,plural,berry,berries} {b, plural, pear}.', ['a' => 1, 'b' => 2]);
        $this->assertSame('!apple berries pears.', $vld->getMessage());
    }

    /**
     * Messages that start with "@" should not be translated.
     */
    public function testNoTranslate() {
        $vld = new Validation();
        $this->assertSame('foo', $vld->translate('@foo'));
    }
}
