<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2017 Vanilla Forums Inc.
 * @license Proprietary
 */

namespace Garden\Schema\Tests;

use Garden\Schema\Schema;
use PHPUnit\Framework\TestCase;

class RealWorldTest extends TestCase {
    /**
     * An optional field should be stripped when provided with an empty string.
     */
    public function testEmptyOptional() {
        $sch = Schema::parse(['a:i?']);

        $valid = $sch->validate(['a' => '']);
        $this->assertSame([], $valid);
    }

    /**
     * An optional field should be stripped when provided with null.
     */
    public function testNullOptional() {
        $sch = Schema::parse(['a:i?']);

        $valid = $sch->validate(['a' => null]);
        $this->assertSame([], $valid);
    }

    /**
     * A nullable field should convert an empty string to null.
     */
    public function testEmptyNullable() {
        $sch = Schema::parse(['a:i|n']);

        $valid = $sch->validate(['a' => '']);
        $this->assertSame(['a' => null], $valid);
    }

    /**
     * A nullable optional field should convert various values to null.
     */
    public function testNullableOptional() {
        $sch = Schema::parse(['a:i|n?']);

        $valid = $sch->validate(['a' => '']);
        $this->assertSame(['a' => null], $valid);

        $valid = $sch->validate(['a' => null]);
        $this->assertSame(['a' => null], $valid);
    }

    /**
     * An optional string field should not strip empty strings.
     */
    public function testOptionalEmptyString() {
        $sch = Schema::parse(['a:s?']);

        $valid = $sch->validate(['a' => '']);
        $this->assertSame(['a' => ''], $valid);
    }

    /**
     * Test schema extending!
     */
    public function testNestedMergedFilteredAdd() {
        $data = [
            'property1' => true,
            'property2' => false,
            'sub-schema' => [
                'sub-property1' => true,
                'sub-property2' => false,
            ]
        ];
        $expectedData = [
            'property1' => true,
            'sub-schema' => [
                'sub-property2' => false,
            ]
        ];

        $subSchema1Definition = [
            'sub-property1:b' => 'Sub property 1',
        ];
        $subSchema2Definition = [
            'sub-property2:b' => 'Sub property 2',
        ];
        $mergedSubSchema = Schema::parse($subSchema1Definition)->merge(
            Schema::parse($subSchema2Definition)
        );

        $schema1Definition = [
            'property1:b' => 'Property 1',
            'sub-schema' => Schema::parse($subSchema1Definition),
        ];
        $schema2Definition = [
            'property2:b' => 'Property 2',
            'sub-schema' => Schema::parse($subSchema2Definition),
        ];
        $mergedSchema = Schema::parse($schema1Definition)->merge(
            Schema::parse($schema2Definition)
        );

        // Buil a schema by extending other schemas!
        $filteredSchema = Schema::parse([
            'property1' => null,
            'sub-schema' => Schema::parse([
                'sub-property2' => null,
            ])->add($mergedSubSchema),
        ])->add($mergedSchema);

        $validatedData = $filteredSchema->validate($data);
        $this->assertEquals($expectedData, $validatedData);
    }
}
