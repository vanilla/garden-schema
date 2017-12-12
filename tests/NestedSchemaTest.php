<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2017 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema\Tests;

use Garden\Schema\Schema;
use Garden\Schema\ValidationException;
use Garden\Schema\ValidationField;
use Garden\Schema\Tests\Fixtures\SchemaValidationFail;

/**
 * Tests for nested object schemas.
 */
class NestedSchemaTest extends AbstractSchemaTest {
    /**
     * Test nested schema validation with valid data.
     */
    public function testNestedValid() {
        $schema = $this->getNestedSchema();

        $validData = [
            'id' => 123,
            'name' => 'Todd',
            'addr' => [
                'street' => '414 rue McGill',
                'city' => 'Montreal',
            ]
        ];

        $isValid = $schema->isValid($validData);
        $this->assertTrue($isValid);
    }

    /**
     * Test a nested schema with som invalid data.
     */
    public function testNestedInvalid() {
        $schema = $this->getNestedSchema();

        $invalidData = [
            'id' => 123,
            'name' => 'Toddo',
            'addr' => [
                'zip' => 'H2Y 2G1'
            ]
        ];

        try {
            $schema->validate($invalidData);
            $this->fail("The data should not be valid.");
        } catch (ValidationException $ex) {
            $validation = $ex->getValidation();
            $this->assertFalse($validation->isValidField('addr.city'), "addr.street should be invalid.");
            $this->assertFalse($validation->isValidField('addr.zip'), "addr.zip should be invalid.");
        }
    }

    /**
     * Verify field names in nested schema error messages appear as expected.
     *
     * @expectedException Garden\Schema\ValidationException
     * @expectedExceptionMessage addr.zip is not a valid integer.
     */
    public function testNestedInvalidMessage() {
        $sch = $this->getNestedSchema();
        $result = $sch->validate([
            'id' => 1,
            'name' => 'Vanilla',
            'addr' => [
                'stree' => '123 Fake St.',
                'city' => 'Nowhere',
                'zip' => false
            ]
        ]);
    }

    /**
     * Test a variety of array item validation scenarios.
     */
    public function testArrayItemsType() {
        $schema = Schema::parse(['arr:a' => 'i']);

        $validData = ['arr' => [1, '2', 3]];
        $this->assertTrue($schema->isValid($validData));

        $invalidData = ['arr' => [1, 'foo', 'bar']];
        $this->assertFalse($schema->isValid($invalidData));

        // Try a custom validator for the items.
        $schema->addValidator('arr[]', function ($value, ValidationField $field) {
            if ($value > 2) {
                $field->addError('{field} must be less than 2.', 422);
            }
        });
        try {
            $schema->validate($validData);
            $this->fail("The data should not validate.");
        } catch (ValidationException $ex) {
            $validation = $ex->getValidation();
            $this->assertFalse($validation->isValidField('arr[2]'));
            $this->assertEquals('arr[2] must be less than 2.', $validation->getMessage());
        }
    }

    /**
     * Test a schema of an array of objects.
     */
    public function testArrayOfObjectsSchema() {
        $schema = $this->getArrayOfObjectsSchema();

        $expected = [
            'type' => 'object',
            'properties' => [
                'rows' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'name' => ['type' => 'string']
                        ],
                        'required' => ['id']
                    ]
                ]
            ],
            'required' => ['rows']
        ];

        $actual = $schema->jsonSerialize();
        $this->assertEquals($expected, $actual);
    }

    /**
     * Test an array of objects to make sure it's valid.
     */
    public function testArrayOfObjectsValid() {
        $schema = $this->getArrayOfObjectsSchema();

        $data = [
            'rows' => [
                ['id' => 1, 'name' => 'Todd'],
                ['id' => 2],
                ['id' => '23', 'name' => 123]
            ]
        ];

        $valid = $schema->validate($data);

        $this->assertInternalType('int', $valid['rows'][2]['id']);
        $this->assertInternalType('string', $valid['rows'][2]['name']);
    }

    /**
     * Test an array of objects that are invalid and make sure the errors are correct.
     */
    public function testArrayOfObjectsInvalid() {
        $schema = $this->getArrayOfObjectsSchema();

        try {
            $missingData = [];
            $schema->validate($missingData);
            $this->fail('$missingData should not be valid.');
        } catch (ValidationException $ex) {
            $this->assertFalse($ex->getValidation()->isValidField('rows'));
        }

        try {
            $notArrayData = ['rows' => 123];
            $schema->validate($notArrayData);
            $this->fail('$notArrayData should not be valid.');
        } catch (ValidationException $ex) {
            $this->assertFalse($ex->getValidation()->isValidField('rows'));
        }

        try {
            $nullItemData = ['rows' => [null]];
            $schema->validate($nullItemData);
        } catch (ValidationException $ex) {
            $this->assertFalse($ex->getValidation()->isValidField('rows[0]'));
        }

        try {
            $invalidRowsData = ['rows' => [
                ['id' => 'foo'],
                ['id' => 123],
                ['name' => 'Todd']
            ]];
            $schema->validate($invalidRowsData);
        } catch (ValidationException $ex) {
            $v4 = $ex->getValidation();
            $this->assertFalse($v4->isValidField('rows[0].id'));
            $this->assertTrue($v4->isValidField('rows[1].id'));
            $this->assertFalse($v4->isValidField('rows[2].id'));
        }

    }

    /**
     * Test throwing an exception when removing unexpected parameters from validated data.
     *
     * @expectedException \Garden\Schema\ValidationException
     */
    public function testValidateException() {
        $this->doValidationBehavior(Schema::VALIDATE_EXTRA_PROPERTY_EXCEPTION);
    }

    /**
     * Call validate on an instance of Schema where the data contains unexpected parameters.
     *
     * @param int $validationBehavior One of the **Schema::VALIDATE_*** constants.
     */
    protected function doValidationBehavior($validationBehavior) {
        $schema = Schema::parse([
            'groupID:i' => 'The ID of the group.',
            'name:s' => 'The name of the group.',
            'description:s' => 'A description of the group.',
            'member:o' => [
                'email:s' => 'The ID of the new member.'
            ]
        ]);
        $schema->setFlags($validationBehavior);

        $data = [
            'groupID' => 123,
            'name' => 'Group Foo',
            'description' => 'A group for testing.',
            'member' => [
                'email' => 'user@example.com',
                'role' => 'Leader',
            ]
        ];

        $valid = $schema->validate($data);
        $this->assertArrayNotHasKey('role', $valid['member']);
    }

    /**
     * Test triggering a notice when removing unexpected parameters from validated data.
     *
     * @expectedException \PHPUnit_Framework_Error_Notice
     */
    public function testValidateNotice() {
        $this->doValidationBehavior(Schema::VALIDATE_EXTRA_PROPERTY_NOTICE);
    }

    /**
     * Test silently removing unexpected parameters from validated data.
     */
    public function testValidateRemove() {
        $this->doValidationBehavior(0);
    }

    /**
     * The schema fields should be case-insensitive and fix the case of incorrect keys.
     */
    public function testCaseInsensitivity() {
        $schema = Schema::parse([
            'obj:o' => [
                'id:i',
                'name:s?'
            ]
        ]);

        $data = [
            'Obj' => [
                'ID' => 123,
                'namE' => 'Frank'
            ]
        ];

        $valid = $schema->validate($data);

        $expected = [
            'obj' => [
                'id' => 123,
                'name' => 'Frank'
            ]
        ];

        $this->assertEquals($expected, $valid);
    }

    /**
     * Test passing a schema instance as details for a parameter.
     */
    public function testSchemaAsParameter() {
        $userSchema = Schema::parse([
            'userID:i',
            'name:s',
            'email:s'
        ]);

        $schema = Schema::parse([
            'name:s' => 'The title of the discussion.',
            'body:s' => 'The body of the discussion.',
            'insertUser' => $userSchema,
            'updateUser?' => $userSchema
        ]);

        $expected = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string', 'description' => 'The title of the discussion.', 'minLength' => 1],
                'body' => ['type' => 'string', 'description' => 'The body of the discussion.', 'minLength' => 1],
                'insertUser' => [
                    'type' => 'object',
                    'properties' => [
                        'userID' => ['type' => 'integer'],
                        'name' => ['type' => 'string', 'minLength' => 1],
                        'email' => ['type' => 'string', 'minLength' => 1]
                    ],
                    'required' => ['userID', 'name', 'email']
                ],
                'updateUser' => [
                    'type' => 'object',
                    'properties' => [
                        'userID' => ['type' => 'integer'],
                        'name' => ['type' => 'string', 'minLength' => 1],
                        'email' => ['type' => 'string', 'minLength' => 1]
                    ],
                    'required' => ['userID', 'name', 'email']
                ]
            ],
            'required' => ['name', 'body', 'insertUser']
        ];

        $this->assertEquals($expected, $schema->jsonSerialize());
    }

    /**
     * Nested schemas should properly validate by calling the nested schema's validation.
     */
    public function testNestedSchemaValidation() {
        $userSchema = Schema::parse([
            'name:s',
            'email:s?'
        ]);

        $schema = Schema::parse([':a' => $userSchema]);

        $clean = $schema->validate([
            ['name' => 'Todd', 'wut' => 'foo'],
            ['name' => 'Ryan', 'email' => 'ryan@example.com']
        ]);
        $this->assertArrayNotHasKey('wut', $clean[0]);

        try {
            $schema->validate([
                ['email' => 'foo@bar.com'],
                ['name' => Schema::parse([])]
            ]);
            $this->fail("The data is not supposed to validate.");
        } catch (ValidationException $ex) {
            $errors = $ex->getValidation()->getErrors();
            $this->assertCount(2, $errors);
            $this->assertEquals('item[0].name is required.', $errors[0]['message']);
            $this->assertEquals('item[1].name is not a valid string.', $errors[1]['message']);
        }
    }

    /**
     * Objects that implement array access should be able to be validated.
     */
    public function testArrayAccessValidation() {
        $schema = Schema::parse([
            'name:s',
            'arr:a' => [
                'id:i',
                'foo:s'
            ]
        ]);

        $data = new \ArrayObject([
            'name' => 'bur',
            'arr' => new \ArrayObject([
                new \ArrayObject(['id' => 1, 'foo' => 'bar']),
                new \ArrayObject(['id' => 2, 'foo' => 'baz']),
            ])
        ]);

        $valid = $schema->validate($data);

        $expected = new \ArrayObject([
            'name' => 'bur',
            'arr' => [
                new \ArrayObject(['id' => 1, 'foo' => 'bar']),
                new \ArrayObject(['id' => 2, 'foo' => 'baz']),
            ]
        ]);

        $this->assertEquals($expected, $valid);
    }

    /**
     * Schemas should be able to recursively point to themselves.
     */
    public function testRecursive() {
        $sch = Schema::parse([
            'name:s',
            'children:a?'
        ]);

        $sch->setField('properties.children.items', $sch);
        $sch->validate(['name' => 'boo']);

        $data = [
            'name' => 'grand',
            'children' => [
                [
                    'name' => 'parent',
                    'children' => [
                        ['name' => 'child']
                    ]
                ]
            ]
        ];

        $valid2 = $sch->validate($data);
        $this->assertEquals($data, $valid2);
    }

    /**
     * Test that a schema that throws an exception in Schema::validate() will have a proper error message.
     */
    public function testNestedSchemaValidationFailureException() {
        $schema = Schema::parse([
            'sub-schema-fail' => new SchemaValidationFail()
        ]);

        $this->expectExceptionMessage('sub-schema-fail is always invalid.');
        $schema->validate(['sub-schema-fail' => null]);
    }
}
