<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2017 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema\Tests;

use Garden\Schema\Schema;
use Garden\Schema\Tests\Fixtures\CustomArray;
use Garden\Schema\Tests\Fixtures\CustomArrayObject;
use Garden\Schema\Tests\Fixtures\TestValidation;
use Garden\Schema\Validation;
use Garden\Schema\ValidationException;

/**
 * Tess for the {@link Schema} object.
 */
class BasicSchemaTest extends AbstractSchemaTest {
    /**
     * An empty schema should validate to anything.
     */
    public function testEmptySchema() {
        $schema = Schema::parse([]);

        $val = [123];
        $r = $schema->validate($val);
        $this->assertSame($val, $r);

        $val = true;
        $r = $schema->validate($val);
        $this->assertSame($val, $r);
    }

    /**
     * An object with no types should validate, but still require values.
     */
    public function testEmptyTypes() {
        $schema = Schema::parse(['a', 'b' => 'Yup', 'c' => []]);

        $data = ['a' => [], 'b' => 1111, 'c' => 'hey!!!'];
        $valid = $schema->validate($data);
        $this->assertSame($data, $valid);

        try {
            $schema->validate([]);
            $this->fail('The data should not be valid.');
        } catch (ValidationException $ex) {
            $errors = $ex->getValidation()->getErrors();
            foreach ($errors as $error) {
                $this->assertSame('missingField', $error['code']);
            }
        }
    }

    /**
     * Test some basic validation.
     */
    public function testAtomicValidation() {
        $schema = $this->getAtomicSchema();
        $data = [
            'id' => 123,
            'name' => 'foo',
            'timestamp' => '13 oct 1975',
            'amount' => '99.50',
            'enabled' => 'yes'
        ];

        $valid = $schema->validate($data);

        $expected = $data;
        $expected['timestamp'] = strtotime($data['timestamp']);
        $expected['enabled'] = true;

        $this->assertEquals($expected, $valid);
    }

    /**
     * Test some data that doesn't need to be be coerced (except one string).
     */
    public function testAtomicValidation2() {
        $schema = $this->getAtomicSchema();
        $data = [
            'id' => 123,
            'name' => 'foo',
            'description' => 456,
            'timestamp' => time(),
            'date' => new \DateTime(),
            'amount' => 5.99,
            'enabled' => true
        ];

        $validated = $schema->validate($data);
        $this->assertEquals($data, $validated);
    }

    /**
     * Test boolean data validation.
     *
     * @param mixed $input The input data.
     * @param bool $expected The expected boolean value.
     * @dataProvider provideBooleanData
     */
    public function testBooleanSchema($input, $expected) {
        $schema = Schema::parse([':b']);
        $valid = $schema->validate($input);
        $this->assertSame($expected, $valid);
    }

    /**
     * Test different date/time parsing.
     *
     * @param mixed $value The value to parse.
     * @param \DateTimeInterface $expected The expected datetime.
     * @dataProvider provideDateTimes
     */
    public function testDateTimeFormats($value, \DateTimeInterface $expected) {
        $schema = Schema::parse([':dt']);

        $valid = $schema->validate($value);
        $this->assertInstanceOf(\DateTimeInterface::class, $valid);
        $this->assertEquals($expected->getTimestamp(), $valid->getTimestamp());
    }

    /**
     * Provide date/time test data.
     *
     * @return array Returns a data provider.
     */
    public function provideDateTimes() {
        $dt = new \DateTimeImmutable('1975-11-11T12:31');

        $r = [
            'string' => [$dt->format('c'), $dt],
            'timestamp' => [$dt->getTimestamp(), $dt]
        ];

        return $r;
    }

    /**
     * Test an array type.
     */
    public function testArrayType() {
        $schema = Schema::parse([':a']);

        $expectedSchema = [
            'type' => 'array'
        ];

        // Basic array without a type.
        $this->assertEquals($expectedSchema, $schema->jsonSerialize());

        $data = [1, 2, 3];
        $this->assertTrue($schema->isValid($data));
        $data = [];
        $this->assertTrue($schema->isValid($data));

        // Array with a description and not a type.
        $expectedSchema['description'] = 'Hello world!';
        $schema = Schema::parse([':a' => 'Hello world!']);
        $this->assertEquals($expectedSchema, $schema->jsonSerialize());

        // Array with an items type.
        unset($expectedSchema['description']);
        $expectedSchema['items']['type'] = 'integer';

        $schema = Schema::parse([':a' => 'i']);
        $this->assertEquals($expectedSchema, $schema->jsonSerialize());

        // Test the longer syntax.
        $expectedSchema['description'] = 'Hello world!';
        $schema = Schema::parse([':a' => [
            'description' => 'Hello world!',
            'items' => ['type' => 'integer']
        ]]);
        $this->assertEquals($expectedSchema, $schema->jsonSerialize());
    }

    /**
     * Test that the schema long form can be used to create a schema.
     */
    public function testLongCreate() {
        $schema = $this->getAtomicSchema();
        $schema2 = new Schema($schema->jsonSerialize());

        $this->assertEquals($schema->jsonSerialize(), $schema2->jsonSerialize());
    }

    /**
     * Test data that is not required, but provided as empty.
     *
     * @param string $shortType The short data type.
     * @dataProvider provideTypesAndData
     */
    public function testNotRequired($shortType) {
        if ($shortType === 'n') {
            $this->markTestSkipped();
        }

        $schema = Schema::parse([
            "col:$shortType?"
        ]);

        $missingData = [];
        $isValid = $schema->isValid($missingData);
        $this->assertTrue($isValid);
        $this->assertArrayNotHasKey('col', $missingData);

        $nullData = ['col' => null];
        $valid = $schema->validate($nullData);
        $this->assertSame([], $valid);
    }

    /**
     * Test data that is not required, but provided as empty.
     *
     * @param string $shortType The short data type.
     * @dataProvider provideTypesAndData
     */
    public function testRequiredEmpty($shortType) {
        // Bools and strings are special cases.
        if (in_array($shortType, ['b', 'n'])) {
            $this->markTestSkipped();
        }

        $schema = Schema::parse([
            "col:$shortType"
        ]);

        $emptyData = ['col' => ''];
        $isValid = $schema->isValid($emptyData);
        $this->assertFalse($isValid);

        $nullData = ['col' => null];
        $isValid = $schema->isValid($nullData);
        $this->assertFalse($isValid);
    }

    /**
     * Test empty boolean values.
     *
     * In general, bools should be cast to false if they are passed, but falsey.
     */
    public function testRequiredEmptyBool() {
        $schema = Schema::parse([
            'col:b'
        ]);
        /* @var Validation $validation */
        $emptyData = ['col' => ''];
        $valid = $schema->validate($emptyData);
        $this->assertFalse($valid['col']);

        $nullData = ['col' => null];
        $isValid = $schema->isValid($nullData);
        $this->assertFalse($isValid);

        $missingData = [];
        try {
            $schema->validate($missingData);
        } catch (ValidationException $ex) {
            $this->assertFalse($ex->getValidation()->isValidField('col'));
        }
    }

    /**
     * Test {@link Schema::requireOneOf()}.
     */
    public function testRequireOneOf() {
        $schema = $this
            ->getAtomicSchema()
            ->requireOneOf(['description', 'enabled']);

        $valid1 = ['id' => 123, 'name' => 'Foo', 'description' => 'Hello'];
        $this->assertTrue($schema->isValid($valid1));

        $valid2 = ['id' => 123, 'name' => 'Foo', 'enabled' => true];
        $this->assertTrue($schema->isValid($valid2));

        $invalid1 = ['id' => 123, 'name' => 'Foo'];
        $this->assertFalse($schema->isValid($invalid1));

        // Test requiring one of nested.
        $schema = $this
            ->getAtomicSchema()
            ->requireOneOf(['description', ['amount', 'enabled']]);

        $this->assertTrue($schema->isValid($valid1));

        $valid3 = ['id' => 123, 'name' => 'Foo', 'amount' => 99, 'enabled' => true];
        $this->assertTrue($schema->isValid($valid3));

        $this->assertFalse($schema->isValid($invalid1));

        $invalid2 = ['id' => 123, 'name' => 'Foo', 'enabled' => true];
        $this->assertFalse($schema->isValid($invalid2));

        // Test requiring 2 of.
        $schema = $this
            ->getAtomicSchema()
            ->requireOneOf(['description', 'amount', 'enabled'], 2);

        $valid4 = ['id' => 123, 'name' => 'Foo', 'description' => 'Hello', 'enabled' => true];
        $this->assertTrue($schema->isValid($valid4));
    }

    /**
     * Require one of on an empty array should fail.
     *
     * @expectedException \Garden\Schema\ValidationException
     */
    public function testRequireOneOfEmpty() {
        $schema = Schema::parse(['a:i?', 'b:i?', 'c:i?'])->requireOneOf(['a', 'b', 'c'], '', 2);

        $r = $schema->validate([]);
    }

    /**
     * Require one of should not fire during sparse validation.
     */
    public function testRequireOneOfSparse() {
        $schema = Schema::parse(['a:i?', 'b:i?', 'c:i?'])->requireOneOf(['a', 'b', 'c'], '', 2);

        $data = [];
        $result = $schema->validate($data, true);
        $this->assertSame($data, $result);

        $data2 = ['a' => 1];
        $result2 = $schema->validate($data2, true);
        $this->assertSame($data2, $result2);
    }

    /**
     * Test a variety of invalid values.
     *
     * @param string $type The type short code.
     * @param mixed $value A value that should be invalid for the type.
     * @dataProvider provideInvalidData
     */
    public function testInvalidValues($type, $value) {
        $schema = Schema::parse([
            "col:$type?"
        ]);
        $strVal = json_encode($value);

        $invalidData = ['col' => $value];
        try {
            $schema->validate($invalidData);
            $this->fail("isValid: type $type with value $strVal should not be valid.");
        } catch (ValidationException $ex) {
            $validation = $ex->getValidation();
            $this->assertFalse($validation->isValidField('col'), "fieldValid: type $type with value $strVal should not be valid.");
        }
    }

    /**
     * Provide a variety of valid boolean data.
     *
     * @return array Returns an array of boolean data.
     */
    public function provideBooleanData() {
        return [
            'false' => [false, false],
            'false str' => ['false', false],
            '0' => [0, false],
            '0 str' => ['0', false],
            'off' => ['off', false],
            'no' => ['no', false],

            'true' => [true, true],
            'true str' => ['true', true],
            '1' => [1, true],
            '1 str' => ['1', true],
            'on' => ['on', true],
            'yes' => ['yes', true]
        ];
    }

    /**
     * Call validate on an instance of Schema where the data contains unexpected parameters.
     *
     * @param int $validationBehavior One of the **Schema::FLAG_*** constants.
     */
    protected function doValidationBehavior($validationBehavior) {
        $schema = Schema::parse([
            'userID:i' => 'The ID of the user.',
            'name:s' => 'The username of the user.',
            'email:s' => 'The email of the user.',
        ]);
        $schema->setFlags($validationBehavior);

        $data = [
            'userID' => 123,
            'name' => 'foo',
            'email' => 'user@example.com',
            'admin' => true,
            'role' => 'Administrator'
        ];

        $valid = $schema->validate($data);
        $this->assertArrayNotHasKey('admin', $valid);
        $this->assertArrayNotHasKey('role', $valid);
    }

    /**
     * Test throwing an exception when removing unexpected parameters from validated data.
     *
     * @expectedException \Garden\Schema\ValidationException
     * @expectedExceptionMessage value has unexpected fields: admin, role.
     * @expectedExceptionCode 422
     */
    public function testValidateException() {
        try {
            $this->doValidationBehavior(Schema::VALIDATE_EXTRA_PROPERTY_EXCEPTION);
        } catch (\Exception $ex) {
            $msg = $ex->getMessage();
            throw $ex;
        }
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
     * Test a custom validation class.
     */
    public function testDifferentValidationClass() {
        $schema = Schema::parse([':i']);
        $schema->setValidationClass(TestValidation::class);

        try {
            $schema->validate('aaa');
        } catch (ValidationException $ex) {
            $this->assertSame('!value is not a valid !integer.', $ex->getMessage());
        }

        $validation = new TestValidation();
        $schema->setValidationClass($validation);
        try {
            $schema->validate('aaa');
        } catch (ValidationException $ex) {
            $this->assertSame('!value is not a valid !integer.', $ex->getMessage());
        }

        $validation->setTranslateFieldNames(true);
        try {
            $schema->validate('aaa');
        } catch (ValidationException $ex) {
            $this->assertSame('!!value is not a valid !integer.', $ex->getMessage());
        }
    }

    /**
     * Test allow null.
     *
     * @param string $short The short type.
     * @param string $long The long type.
     * @param mixed $sample As sample value.
     * @dataProvider provideTypesAndData
     */
    public function testAllowNull($short, $long, $sample) {
        $schema = Schema::parse([":$short|n"]);

        $null = $schema->validate(null);
        $this->assertNull($null);

        $clean = $schema->validate($sample);
        $this->assertSame($sample, $clean);
    }

    /**
     * Test default values.
     */
    public function testDefault() {
        $schema = Schema::parse([
            'prop:s' => ['default' => 'foo']
        ]);

        $valid = $schema->validate([]);
        $this->assertSame(['prop' => 'foo'], $valid);

        $valid = $schema->validate([], true);
        $this->assertSame([], $valid);
    }

    /**
     * Default values for non-required fields.
     */
    public function testDefaultNotRequired() {
        $schema = Schema::parse([
            'prop:s?' => ['default' => 'foo']
        ]);

        $valid = $schema->validate([]);
        $this->assertSame(['prop' => 'foo'], $valid);

        $valid = $schema->validate([], true);
        $this->assertSame([], $valid);
    }

    public function testBoolFalse() {
        $schema = Schema::parse(['bool:b']);

        $valid = $schema->validate(['bool' => false]);
        $this->assertFalse($valid['bool']);
    }

    /**
     * Objects that implement **ArrayAccess** should be returned as valid copies.
     *
     * @param string $class The name of the class to test.
     * @dataProvider provideArrayObjectClasses
     */
    public function testArrayObjectResult($class) {
        $schema = Schema::parse([':o']);

        $fn = function () use ($class) {
            $r = new $class();
            $r['a'] = 1;
            $r['b'] = 2;

            return $r;
        };

        $expected = $fn();
        $valid = $schema->validate($expected);

        $this->assertInstanceOf($class, $valid);
        /* @var \ArrayObject $valid */
        $this->assertNotSame($expected, $valid);
        $this->assertEquals($expected->getArrayCopy(), $valid->getArrayCopy());

    }

    /**
     * Objects that implement **ArrayAccess** should be returned as valid copies.
     *
     * @param string $class The name of the class to test.
     * @dataProvider provideArrayObjectClasses
     */
    public function testArrayObjectResultWithProperties($class) {
        $schema = Schema::parse(['a:i', 'b:s']);

        $fn = function () use ($class) {
            $r = new $class();
            $r['a'] = 1;
            $r['b'] = 'foo';

            return $r;
        };

        $expected = $fn();
        $valid = $schema->validate($expected);

        $this->assertInstanceOf($class, $valid);
        /* @var \ArrayObject $valid */
        $this->assertNotSame($expected, $valid);
        $this->assertEquals($expected->getArrayCopy(), $valid->getArrayCopy());
    }

    /**
     * Provide sample array access classes.
     *
     * @return array Returns a data provider array.
     */
    public function provideArrayObjectClasses() {
        $r = [
            [\ArrayObject::class],
            [CustomArrayObject::class],
            [CustomArray::class]
        ];

        return array_column($r, null, 0);
    }

    /**
     * Old style **allowNull** fields should be converted into a union type including null.
     */
    public function testAllowNullBC() {
        $sch = Schema::parse([
            'photo:s' => [
                'allowNull' => true,
                'minLength' => 0,
                'description' => 'Raw photo field value from the user record.'
            ]
        ]);

        $this->assertArrayNotHasKey('allowNull', $sch->getField('properties.photo'));
        $this->assertEquals(['string', 'null'], $sch->getField('properties.photo.type'));
    }

    /**
     * Test some null validation.
     */
    public function testNull() {
        $sch = Schema::parse(['a:n', 'b:n?']);

        $expected = ['a' => null, 'b' => null];
        $r = $sch->validate(['a' => null, 'b' => null]);
        $this->assertEquals($expected, $r);

        $this->assertFalse($sch->isValid(['a' => 1]));
    }

    /**
     * Test validation on mixed empty string null types.
     */
    public function testStringOrNull() {
        $sch = new Schema([
            'type' => ['string', 'null']
        ]);

        $r = $sch->validate('');
        $this->assertSame('', $r);

        $r = $sch->validate(null);
        $this->assertSame(null, $r);
    }
}
