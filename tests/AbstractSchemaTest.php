<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema\Tests;

use PHPUnit\Framework\Error\Error;
use PHPUnit\Framework\Error\Notice;
use PHPUnit\Framework\TestCase;
use Garden\Schema\Schema;
use Garden\Schema\Validation;
use PHPUnit\Framework\Warning;

/**
 * Base class for schema tests.
 */
abstract class AbstractSchemaTest extends TestCase {
    private $expectedErrors;

    /**
     * Clear out the errors array.
     */
    protected function setUp() {
        $this->expectedErrors = [];
        set_error_handler([$this, "errorHandler"]);
    }

    /**
     * Track errors that occur during testing.
     *
     * The handler allows test methods to explicitly expect errors without failing. If an error is not expected then
     * it will be thrown as usual.
     *
     * @param int $number The number of the error.
     * @param string $message The error message.
     * @param string $file The file the error occurred in.
     * @param int $line The line the error occurred on.
     * @throws \Throwable Throws an exception when the error was not expected.
     */
    public function errorHandler($number, $message, $file, $line) {
        // Look for an expected error.
        foreach ($this->expectedErrors as $i => $row) {
            list($no, $str) = $row;

            if (($number === $no || $no === null) && ($message === $str || $str === null)) {
                unset($this->expectedErrors[$i]);
                return;
            }
        }

        switch ($number) {
            case E_NOTICE:
            case E_USER_NOTICE:
            case E_DEPRECATED:
            case E_USER_DEPRECATED:
                throw new Notice($message, $number, $file, $line);
            case E_WARNING:
            case E_USER_WARNING:
                throw new Warning($message, $number);
            case E_ERROR:
            case E_USER_ERROR:
                throw new Error($message, $number, $file, $line);
            default:
                // No error was found so throw an exception.
                throw new \ErrorException($message, $number, $number, $file, $line);
        }
    }

    /**
     * Assert than an error has occurred.
     *
     * @param string $errstr The desired error string.
     * @param int $errno The desired error number.
     */
    public function expectError($errstr, $errno) {
        $this->expectedErrors[] = [$errno, $errstr];
    }

    /**
     * Assert than an error has occurred.
     *
     * @param int $errno The desired error number.
     */
    public function expectErrorNumber($errno) {
        $this->expectError(null, $errno);
    }


    /**
     * Provides all of the schema types.
     *
     * @return array Returns an array of types suitable to pass to a test method.
     */
    public function provideTypesAndData() {
        $result = [
            'array' => ['a', 'array', [1, 2, 3]],
            'object' => ['o', 'object', ['foo' => 'bar']],
            'integer' => ['i', 'integer', 123],
            'string' => ['s', 'string', 'hello'],
            'number' => ['f', 'number', 12.3],
            'boolean' => ['b', 'boolean', true],
            'timestamp' => ['ts', 'timestamp', time()],
            'datetime' => ['dt', 'datetime', new \DateTimeImmutable()],
            'null' => ['n', 'null', null],
        ];
        return $result;
    }

    /**
     * Provide just the non-null types and data.
     *
     * @return array Returns a data provider array.
     */
    public function provideNonNullTypesAndData() {
        $r = $this->provideTypesAndData();
        unset($r['null']);
        return $r;
    }

    /**
     * Provides schema types without null.
     *
     * @return array Returns a data provider array.
     */
    public function provideTypesAndDataNotNull(): array {
        $result = $this->provideTypesAndData();
        unset($result['null']);

        return $result;
    }

    /**
     * Provide a variety of invalid data for the supported types.
     *
     * @return array Returns an data set with rows in the form [short type, value].
     */
    public function provideInvalidData() {
        $result = [
            ['a', false],
            ['a', 123],
            ['a', 'foo'],
            ['a', ['bar' => 'baz']],
            ['o', false],
            ['o', 123],
            ['o', 'foo'],
            ['o', [1, 2, 3]],
            ['i', false],
            ['i', 'foo'],
            ['i', [1, 2, 3]],
            ['s', false],
            ['s', [1, 2, 3]],
            ['f', false],
            ['f', 'foo'],
            ['f', [1, 2, 3]],
            ['b', 123],
            ['b', 'foo'],
            ['b', [1, 2, 3]],
            ['ts', false],
            ['ts', 'foo'],
            ['ts', [1, 2, 3]],
            ['dt', (string)time()],
            ['dt', 'foo'],
            ['dt', [1, 2, 3]]
        ];

        return $result;
    }

    /**
     * Get a schema of atomic types.
     *
     * @return Schema Returns the schema of atomic types.
     */
    public function getAtomicSchema() {
        $schema = Schema::parse([
            'id:i',
            'name:s' => 'The name of the object.',
            'description:s?',
            'timestamp:ts?',
            'date:dt?',
            'amount:f?',
            'enabled:b?',
        ]);

        return $schema;
    }

    /**
     * Get a basic nested schema for testing.
     *
     * @return Schema Returns a new schema for testing.
     */
    public function getNestedSchema() {
        $schema = Schema::parse([
            'id:i',
            'name:s',
            'addr:o' => [
                'street:s?',
                'city:s',
                'zip:i?'
            ]
        ]);

        return $schema;
    }

    /**
     * Get a schema that consists of an array of objects.
     *
     * @return Schema Returns the schema.
     */
    public function getArrayOfObjectsSchema() {
        $schema = Schema::parse([
            'rows:a' => [
                'id:i',
                'name:s?'
            ]
        ]);

        return $schema;
    }

    /**
     * Assert that a validation object has an error code for a field.
     *
     * @param Validation $validation The validation object to inspect.
     * @param string $field The field to look for.
     * @param string $code The error code that must be present.
     */
    public function assertFieldHasError(Validation $validation, $field, $code) {
        $name = $field ?: 'value';

        if ($validation->isValidField($field)) {
            $this->fail("The $name does not have any errors.");
            return;
        }

        $codes = [];
        foreach ($validation->getFieldErrors($field) as $error) {
            if ($code === $error['code']) {
                $this->assertEquals($code, $error['code']); // Need at least one assertion.
                return;
            }
            $codes[] = $error['code'];
        }

        $has = implode(', ', $codes);
        $this->fail("The $name does not have the $code error (has $has).");
    }
}
