<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema\Tests;

use Garden\Schema\Schema;
use Garden\Schema\Tests\Fixtures\TestValidation;
use Garden\Schema\Validation;
use Garden\Schema\ValidationException;

/**
 * Tests for the `Validation` class' error message formatting.
 */
class ValidationErrorMesageTest extends AbstractSchemaTest {

    /**
     * An empty validation object should return no error message.
     */
    public function testNoErrorMessage() {
        $vld = new TestValidation();

        $this->assertEmpty($vld->getFullMessage());
    }

    /**
     * A main error message should be returned even if there are no errors.
     */
    public function testMainErrorMessage() {
        $vld = $this->createErrors('Foo');

        $this->assertSame('!Foo', $vld->getFullMessage());
    }

    /**
     * Create some test data.
     *
     * @param string $main The main error message.
     * @param int $fieldlessErrorCount The number of fieldless errors.
     * @param int $fieldErrorCount The number of field errors.
     * @param int $fieldCount The number of fields.
     * @return TestValidation Returns a test validation object populated with data.
     */
    private function createErrors(string $main, int $fieldlessErrorCount = 0, int $fieldErrorCount = 0, int $fieldCount = 1): TestValidation {
        $vld = new TestValidation();

        if ($main) {
            $vld->setMainMessage($main);
        }

        for ($i = 1; $i <= $fieldlessErrorCount; $i++) {
            $vld->addError('', "error $i");
        }

        for ($field = 1; $field <= $fieldCount; $field++) {
            for ($i = 1; $i <= $fieldErrorCount; $i++) {
                $vld->addError("Field $field", "error $i");
            }
        }

        return $vld;
    }

    /**
     * Test one error on the empty field.
     */
    public function testOneFieldlessError() {
        $vld = $this->createErrors('', 1);

        $this->assertSame('!error 1', $vld->getFullMessage());
    }

    /**
     * Test two errors on an empty field.
     */
    public function testTwoFieldlessErrors() {
        $vld = $this->createErrors('', 2);

        $this->assertSame("!error 1 !error 2", $vld->getMainMessage());
    }

    /**
     * Test one error on a field.
     */
    public function testOneFieldError() {
        $vld = $this->createErrors('', 0, 1);
        $this->assertSame([
            'message' => "!Validation failed.",
            "code" => 400,
            "errors" => [
                'Field 1' => [
                    ['error' => 'error 1', 'message' => '!error 1']
                ]
            ]
        ], $vld->jsonSerialize());
    }

    /**
     * Test two errors on a field.
     */
    public function testTwoFieldErrors() {
        $vld = $this->createErrors('', 0, 2);
        $this->assertSame("![Field 1]:\n  !error 1\n  !error 2", $vld->getFullMessage());
    }

    /**
     * The main error should go above field errors.
     */
    public function testMainAndFieldError() {
        $vld = $this->createErrors('Failed', 0, 1);

        $this->assertSame("!Failed\n\n![Field 1]: !error 1", $vld->getFullMessage());
    }

    /**
     * Validation's JSON should show a default success message.
     */
    public function testNoErrorJSON() {
        $vld = new TestValidation();
        $json = $vld->jsonSerialize();
        $this->assertSame(['message' => '!Validation succeeded.', 'code' => 200, 'errors' => []], $json);
    }

    /**
     * The main validation message should come through in JSON.
     */
    public function testMainMessageJSON() {
        $vld = $this->createErrors('Foo');
        $json = $vld->jsonSerialize();
        $this->assertSame(['message' => '!Foo', 'code' => 200, 'errors' => []], $json);
    }

    /**
     * Test one fieldless error.
     */
    public function testOneFieldlessErrorJSON() {
        $vld = $this->createErrors('', 1);
        $json = $vld->jsonSerialize();
        $this->assertEquals(['message' => '!Validation failed.', 'code' => 400, 'errors' => [
            '' => [['error' => 'error 1', 'message' => '!error 1']]
        ]], $json);
    }

    /**
     * Test a more complex error in JSON.
     */
    public function testComplexErrorJSON() {
        $vld = $this->createErrors('Foo', 0, 2, 2);
        $vld->addError('Field X', 'error 1', ['code' => 433, 'messageCode' => 'foo']);
        $json = $vld->jsonSerialize();
        $this->assertEquals(['message' => '!Foo', 'code' => 433, 'errors' => [
            'Field 1' => [
                ['error' => 'error 1', 'message' => '!error 1'],
                ['error' => 'error 2', 'message' => '!error 2']
            ],
            'Field 2' => [
                ['error' => 'error 1', 'message' => '!error 1'],
                ['error' => 'error 2', 'message' => '!error 2']
            ],
            'Field X' => [
                ['error' => 'error 1', 'code' => 433, 'message' => '!foo'],
            ],
        ]], $json);
    }

    /**
     * Concatenated messages may add punctuation to error messages without it.
     *
     * @param string $error The error.
     * @param string $expected The expected concat message.
     * @dataProvider provideConcatPunctuationTests
     */
    public function testConcatPunctuation(string $error, string $expected) {
        $vld = new Validation();

        $vld->addError('', $error);
        $this->assertSame($expected, $vld->getConcatMessage(''));
    }

    /**
     * The JSON data from validation should validate against the **ValidationError** schema.
     *
     * @param Validation $vld The validation data to check.
     * @dataProvider provideValidationData
     */
    public function testValidationJSONAgainstSchema(Validation $vld) {
        $sch = $this->loadOpenApiSchema('#/components/schemas/ValidationError');
        $json = $vld->jsonSerialize();

        $valid = $sch->validate($json);
        $this->assertSortedArrays($json, $valid);
    }

    /**
     * Provide some dummy validation data.
     *
     * @return array Returns a data provider array.
     */
    public function provideValidationData(): array {
        $r = [
            'success' => [new Validation()],
            'main message' => [$this->createErrors('Error')],
            'one fieldless' => [$this->createErrors('', 1)],
            'two fieldless' => [$this->createErrors('', 2)],
            'fields' => [$this->createErrors('', 0, 2)],
            'all' => [$this->createErrors('Error', 2, 2, 2)],
        ];

        return $r;
    }

    /**
     * Provide some auto punctuation tests.
     *
     * @return array Returns a data provider.
     */
    public function provideConcatPunctuationTests(): array {
        $r = [
            ['foo', 'foo.'],
            ['foo.', 'foo.'],
            ['foo?', 'foo?'],
            ['foo!', 'foo!'],
        ];

        return array_column($r, null, 0);
    }
}
