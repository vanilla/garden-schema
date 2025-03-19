<?php
/*
 * @author Adam Charron <acharron@higherlogic.com>
 * @copyright 2009-2025 Higher Logic
 * @license MIT
 */

namespace Garden\Schema\Tests;

use Garden\Schema\Schema;
use PHPUnit\Framework\TestCase;
use Garden\Schema\Validation;
use Garden\Schema\Tests\Fixtures\TestValidation;

/**
 * Version 2.x made some huge backwards incompatibilities.
 *
 * Version 4.x undoes these so vanilla/vanilla-cloud can upgrade.
 *
 *
 */
class Version1CompatTest extends TestCase {

    /**
     * @return void
     */
    public function testDateTimeAndTimestampAliases(): void {
        $schema = Schema::parse([
            "tsShortform:ts?",
            "tsLongform?" => [
                "type" => "timestamp",
            ],
            "dtShortform:dt?",
            "dtLongform" => [
                "type" => "datetime",
            ],
        ]);

        $schemaArray = $schema->getSchemaArray();

        $this->assertEquals([
            "type" => "object",
            "properties" => [
                "tsShortform" => [
                      "type" => "integer",
                    "format" => "timestamp",
                ],
                "tsLongform" => [
                    "type" => "integer",
                    "format" => "timestamp",
                ],
                "dtShortform" => [
                    "type" => "string",
                    "format" => "date-time",
                ],
                "dtLongform" => [
                    "type" => "string",
                    "format" => "date-time",
                ],
            ],
            "required" => ["dtLongform"],
        ], $schemaArray);
    }

    /**
     * @return void
     */
    public function testGetFieldWithDot(): void {
        $schema = Schema::parse([
            "foo" => Schema::parse([
                "nested" => Schema::parse([
                    "bar:s", 
                ])
            ])
        ]);
        
        $this->assertEquals("string", $schema->getField("properties.foo.properties.nested.properties.bar.type"));
    }

    /**
     * @return void
     */
    public function testValidateWholeObjectWithEmptyString(): void {
        
        $arrFromValidator = null;
        
        $schema = Schema::parse([
            "foo:s",
            "bar:s",
        ])->addValidator("", function (array $arr) use (&$arrFromValidator) {
            $arrFromValidator = $arr;
        });
        
        $myVal = [
            "foo" => "hello",
            "bar" => "world",
        ];
        
        $schema->validate($myVal);
        
        $this->assertEquals($myVal, $arrFromValidator);
    }

    /**
     * @return void
     */
    public function testValidateDotNotationProperty(): void {

        $arrFromValidator = null;
        $fooFromValidator = null;
        $schema = Schema::parse([
            "nested" => [
                "type" => "object",
                "properties" => [
                    "foo" => [
                        "type" => "string",
                    ],
                    "bar" => [
                        "type" => "string",
                    ],
                ]
            ]
        ])->addValidator("nested", function (array $arr) use (&$arrFromValidator) {
            $arrFromValidator = $arr;
        })->addValidator("nested.foo", function ($foo) use (&$fooFromValidator) {
            $fooFromValidator = $foo;
        });
        

        $myVal = [
            "foo" => "hello",
            "bar" => "world",
        ];

        $schema->validate(["nested" => $myVal]);

        $this->assertEquals($myVal, $arrFromValidator);
        $this->assertEquals("hello", $fooFromValidator);
    }
    
    public function testValidateSparseLegacy() {
        $schema = Schema::parse([
            "foo:s",
            "bar:s",
        ]);
        
        // Legacy of of specifying sparse.
        $result = $schema->validate(["foo" => "hello"], true);
        
        $this->assertEquals([
            "foo" => "hello",
        ], $result);
        
        $this->assertTrue($schema->isValid(["foo" => "hello"], true));
    }
}