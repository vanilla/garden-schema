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
     * @dataProvider provideMinLengthTests
     */
    public function testMinLength($str, $code, $minLength = 3) {
        $schema = Schema::parse(['str:s' => ['minLength' => $minLength]]);

        try {
            $schema->validate(['str' => $str]);

            if (!empty($code)) {
                $this->fail("'$str' shouldn't validate against a min length of $minLength.");
            } else {
                $this->assertGreaterThanOrEqual($minLength, strlen($str));
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
            'empty 0' => ['', '', 0]
        ];

        return $r;
    }

    /**
     * Test string max length constraints.
     *
     * @param string $str The string to test.
     * @param string $code The expected error code, if any.
     * @param int $maxLength The max length to test.
     * @dataProvider provideMaxLengthTests
     */
    public function testMaxLength($str, $code = '', $maxLength = 3) {
        $schema = Schema::parse(['str:s?' => ['maxLength' => $maxLength]]);

        try {
            $schema->validate(['str' => $str]);

            if (!empty($code)) {
                $this->fail("'$str' shouldn't validate against a max length of $maxLength.");
            } else {
                $this->assertLessThanOrEqual($maxLength, strlen($str));
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
