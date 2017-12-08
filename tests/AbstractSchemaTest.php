<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2017 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema\Tests;

use PHPUnit\Framework\TestCase;
use Garden\Schema\Schema;
use Garden\Schema\Validation;

/**
 * Base class for schema tests.
 */
abstract class AbstractSchemaTest extends TestCase {
    /**
     * Provides all of the schema types.
     *
     * @return array Returns an array of types suitable to pass to a test method.
     */
    public function provideTypesAndData() {
        $result = [
//            'array' => ['a', 'array', [1, 2, 3]],
//            'object' => ['o', 'object', ['foo' => 'bar']],
//            'integer' => ['i', 'integer', 123],
//            'string' => ['s', 'string', 'hello'],
//            'number' => ['f', 'number', 12.3],
//            'boolean' => ['b', 'boolean', true],
//            'timestamp' => ['ts', 'timestamp', time()],
//            'datetime' => ['dt', 'datetime', new \DateTimeImmutable()],
            'null' => ['n', 'null', null],
        ];
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
