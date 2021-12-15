<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2017 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema\Tests;

use DateTime;
use Garden\Schema\Schema;
use Garden\Schema\Validation;
use Garden\Schema\ValidationException;

/**
 * Test string validation properties.
 */
class StringValidationTest extends AbstractSchemaTest {
    /**
     * Test string min length constraints.
     *
     * @param string $str The string to test.
     * @param string $code The expected error code, if any.
     * @param int $minLength The min length to test.
     * @param int $flags Flags to set on the schema.
     *
     * @dataProvider provideMinLengthTests
     */
    public function testMinLength($str, $code, $minLength = 3, int $flags = null) {
        $schema = Schema::parse(['str:s' => ['minLength' => $minLength]]);
        if ($flags) {
            $schema->setFlags($flags);
        }

        try {
            $schema->validate(['str' => $str]);

            if (!empty($code)) {
                $this->fail("'$str' shouldn't validate against a min length of $minLength.");
            } else {
                // Everything validated correctly.
                $this->assertTrue(true);
            }
        } catch (ValidationException $ex) {
            $this->assertFieldHasError($ex->getValidation(), 'str', $code);
        }
    }

    /**
     * Provide test data for {@link testMinLength()}.
     *
     * @return array Returns a data provider array.
     */
    public function provideMinLengthTests() {
        $r = [
            'empty' => ['', 'minLength'],
            'ab' => ['ab', 'minLength'],
            'abc' => ['abc', ''],
            'abcd' => ['abcd', ''],
            'empty 1' => ['', 'missingField', 1],
            'empty 0' => ['', '', 0],
            'unicode as bytes success' => ['😱', 'minLength', 4],
            'unicode as unicode fail' => ['😱', 'minLength', 2, Schema::VALIDATE_STRING_LENGTH_AS_UNICODE],
            'unicode as unicode success' => ['😱', '', 1, Schema::VALIDATE_STRING_LENGTH_AS_UNICODE],
        ];

        return $r;
    }

    /**
     * Test string max length constraints.
     *
     * @param string $str The string to test.
     * @param string $code The expected error code, if any.
     * @param int $maxLength The max length to test.
     *
     * @dataProvider provideMaxLengthTests
     */
    public function testMaxLength($str, string $code = '', int $maxLength = 3) {
        $schema = Schema::parse(['str:s?' => [
            'maxLength' => $maxLength,
        ]]);

        try {
            $schema->validate(['str' => $str]);

            if (!empty($code)) {
                $this->fail("'$str' shouldn't validate against a max length of $maxLength.");
            } else {
                // Everything validated correctly.
                $this->assertTrue(true);
            }
        } catch (ValidationException $ex) {
            $this->assertFieldHasError($ex->getValidation(), 'str', $code);
        }
    }

    /**
     * Provide test data for {@link testMaxLength()}.
     *
     * @return array Returns a data provider array.
     */
    public function provideMaxLengthTests() {
        $r = [
            'empty' => [''],
            'ab' => ['ab'],
            'abc' => ['abc'],
            'abcd' => ['abcd', 'maxLength'],
        ];

        return $r;
    }

    /**
     * Test byte length validation.
     *
     * @param array $value
     * @param string|array|null $exceptionMessages Null, an expected exception message, or multiple expected exception messages.
     * @param bool $forceByteLength Set this to true to force all maxLengths to be byte length.
     *
     * @dataProvider provideByteLengths
     */
    public function testByteLengthValidation(array $value, $exceptionMessages = null, bool $forceByteLength = false) {
        $schema = Schema::parse([
            'justLength:s?' => [
                'maxLength' => 4,
            ],
            'justByteLength:s?' => [
                'maxByteLength' => 8,
            ],
            'mixedLengths:s?' => [
                'maxLength' => 4,
                'maxByteLength' => 6
            ],
        ]);
        if ($forceByteLength) {
            $schema->setFlag(Schema::VALIDATE_STRING_LENGTH_AS_UNICODE, false);
        }

        try {
            $schema->validate($value);
            // We were expecting success.
            $this->assertTrue(true);
        } catch (ValidationException $e) {
            if ($exceptionMessages !== null) {
                $actual = $e->getMessage();
                $exceptionMessages = is_array($exceptionMessages) ? $exceptionMessages : [$exceptionMessages];
                foreach ($exceptionMessages as $expected) {
                    $this->assertContains($expected, $actual);
                }
            } else {
                throw $e;
            }
        }
    }

    /**
     * @return array
     */
    public function provideByteLengths() {
        return [
            'maxLength - short' => [['justLength' => '😱']],
            'maxLength - equal' => [['justLength' => '😱😱😱😱']],
            'maxLength - long' => [['justLength' => '😱😱😱😱😱'], '1 characters too long'],
            'byteLength - short' => [['justByteLength' => '😱']],
            'byteLength - equal' => [['justByteLength' => '😱😱']],
            'byteLength - long' => [['justByteLength' => '😱😱a'], '1 byte too long'],
            'mixedLengths - short' => [['mixedLengths' => '😱']],
            'mixedLengths - equal' => [['mixedLengths' => '😱aa']],
            'mixedLengths - long bytes' => [['mixedLengths' => '😱😱'], '2 bytes too long'],
            'mixedLengths - long chars' => [['mixedLengths' => 'aaaaa'], '1 characters too long'],
            'mixedLengths - long chars - long bytes' => [['mixedLengths' => '😱😱😱😱😱'], ["1 characters too long", "14 bytes too long."]],
            'byteLength flag - short' => [['justLength' => '😱'], null, true],
            'byteLength flag - long' => [['justLength' => '😱😱😱😱'], '12 bytes too long', true],
            'byteLength property is preferred over byte length flag' => [['mixedLengths' => '😱😱'], '2 bytes too long', true]
        ];
    }

    /**
     * Test string pattern constraints.
     *
     * @param string $str The string to test.
     * @param string $code The expected error code, if any.
     * @param string $pattern The pattern to test.
     * @dataProvider providePatternTests
     */
    public function testPattern($str, $code = '', $pattern = '^[a-z]o+$') {
        $schema = Schema::parse(['str:s?' => ['pattern' => $pattern]]);

        try {
            $schema->validate(['str' => $str]);

            if (!empty($code)) {
                $this->fail("'$str' shouldn't validate against a pattern of $pattern.");
            } else {
                $this->assertRegExp("/{$pattern}/", $str);
            }
        } catch (ValidationException $ex) {
            $this->assertFieldHasError($ex->getValidation(), 'str', $code);
        }
    }

    /**
     * Provide test data for {@link testPattern()}.
     *
     * @return array Returns a data provider array.
     */
    public function providePatternTests() {
        $r = [
            'empty' => ['', 'invalid'],
            'fo' => ['fo', ''],
            'foo' => ['foooooooooo', ''],
            'abcd' => ['abcd', 'invalid'],
        ];

        return $r;
    }

    /**
     * Test the enum constraint.
     *
     * @expectedException \Garden\Schema\ValidationException
     * @expectedExceptionMessage value must be one of: one, two, three, null.
     * @expectedExceptionCode 422
     */
    public function testEnum() {
        $enum = ['one', 'two', 'three', null];
        $schema = Schema::parse([':s|n' => ['enum' => $enum]]);

        foreach ($enum as $str) {
            $this->assertTrue($schema->isValid($str));
        }

        $schema->validate('four');
    }

    /**
     * Test a required empty string with a min length of 0.
     */
    public function testRequiredEmptyString() {
        $schema = Schema::parse([
            'col:s' => ['minLength' => 0]
        ]);

        $emptyData = ['col' => ''];
        $valid = $schema->validate($emptyData);
        $this->assertEmpty($valid['col']);
        $this->assertInternalType('string', $valid['col']);

        $nullData = ['col' => null];
        $isValid = $schema->isValid($nullData);
        $this->assertFalse($isValid);

        $missingData = [];
        $isValid = $schema->isValid($missingData);
        $this->assertFalse($isValid);
    }

    /**
     * Test different date/time parsing.
     *
     * @param mixed $value The value to parse.
     * @param string $expected The expected datetime.
     * @dataProvider provideDateTimeFormatTests
     */
    public function testDateTimeFormat($value, $expected) {
        $schema = Schema::parse([':s' => ['format' => 'date-time']]);

        $valid = $schema->validate($value);
        $this->assertEquals($expected, $valid);
    }

    /**
     * Provide date strings in various formats.
     *
     * @return array Returns a data provider array.
     */
    public function provideDateTimeFormatTests() {
        $dt = new \DateTimeImmutable();
        $dtStr = $dt->format(DateTime::RFC3339);

        $r = [
            $dt->format(DateTime::ATOM),
            $dt->format(DateTime::COOKIE),
            $dt->format(DateTime::RFC822),
            $dt->format(DateTime::RFC850),
            $dt->format(DateTime::RFC850),
            $dt->format(DateTime::W3C),
        ];

        $r = array_map(function ($v) use ($dtStr) {
            return [$v, $dtStr];
        }, $r);
        $r = array_column($r, null, 0);
        return $r;
    }

    /**
     * Test the email string format.
     */
    public function testEmailFormat() {
        $schema = Schema::parse([':s' => ['format' => 'email']]);

        $this->assertTrue($schema->isValid('todd@example.com'));
        $this->assertTrue($schema->isValid('todd+foo@example.com'));
        $this->assertFalse($schema->isValid('todd@example'));
    }

    /**
     * Test the IPv4 format.
     */
    public function testIPv4Format() {
        $schema = Schema::parse([':s' => ['format' => 'ipv4']]);

        $this->assertTrue($schema->isValid('127.0.0.1'));
        $this->assertTrue($schema->isValid('192.168.5.5'));
        $this->assertFalse($schema->isValid('todd@example'));
    }

    /**
     * Test the IPv6 format.
     */
    public function testIPv6Format() {
        $schema = Schema::parse([':s' => ['format' => 'ipv6']]);

        $this->assertTrue($schema->isValid('2001:0db8:0a0b:12f0:0000:0000:0000:0001'));
        $this->assertTrue($schema->isValid('2001:db8::1'));
        $this->assertFalse($schema->isValid('127.0.0.1'));
    }

    /**
     * Test the IPv6 format.
     */
    public function testIPFormat() {
        $schema = Schema::parse([':s' => ['format' => 'ip']]);

        $this->assertTrue($schema->isValid('2001:0db8:0a0b:12f0:0000:0000:0000:0001'));
        $this->assertTrue($schema->isValid('2001:db8::1'));
        $this->assertTrue($schema->isValid('127.0.0.1'));
        $this->assertFalse($schema->isValid('todd@example'));
    }

    /**
     * Test the IPv6 format.
     *
     * @param string $uri A URI.
     * @param bool $valid Whether the URI should be valid or invalid.
     * @dataProvider provideUris
     */
    public function testUriFormat($uri, $valid = true) {
        $schema = Schema::parse([':s' => ['format' => 'uri']]);

        if ($valid) {
            $this->assertTrue($schema->isValid($uri));
        } else {
            $this->assertFalse($schema->isValid($uri));
        }
    }

    /**
     * Provide test data for {@link testUriFormat()}.
     *
     * @return array Returns a data provider.
     */
    public function provideUris() {
        $r = [
            ['ftp://ftp.is.co.za/rfc/rfc1808.txt1'],
            ['http://www.ietf.org/rfc/rfc2396.txt'],
            ['ldap://[2001:db8::7]/c=GB?objectClass?one'],
            ['mailto:John.Doe@example.com'],
            ['news:comp.infosystems.www.servers.unix'],
            ['telnet://192.0.2.16:80/'],
            ['aaa', false]
        ];

        return array_column($r, null, 0);
    }
}
