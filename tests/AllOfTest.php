<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema\Tests;

use Garden\Schema\ArrayRefLookup;
use Garden\Schema\ParseException;
use Garden\Schema\Schema;
use Garden\Schema\ValidationException;
use PHPUnit\Framework\TestCase;

/**
 * Tests allOf
 */
class AllOfTest extends TestCase {

    /**
     * @var ArrayRefLookup
     */
    private $lookup;

    public function setUp(): void {
        parent::setUp();

        $arr = [
            'components' => [
                'schemas' => [
                    'Pet' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => [
                                'type' => 'string',
                            ]
                        ]
                    ],
                    'Cat' => [
                        'allOf' => [
                            ['$ref' => '#/components/schemas/Pet'],
                            [
                                'type' => 'object',
                                'properties' => [
                                    'likes' => [
                                        'type' => 'string',
                                        'enum' => ['milk', 'purring'],
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'Dog' => [
                        'allOf' => [
                            ['$ref' => '#/components/schemas/Pet'],
                            [
                                'type' => 'object',
                                'properties' => [
                                    'isGoodBoy' => [
                                        'type' => 'boolean',
                                        'default' => true,
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'Puppy' => [
                        'allOf' => [
                            ['$ref' => '#/components/schemas/Dog'],
                            [
                                'type' => 'object',
                                'properties' => [
                                    'canRun' => [
                                        'type' => 'boolean',
                                        'default' => false,
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'Invalid1' => [
                        'allOf' => [
                            ['$ref' => '#/components/schemas/Dog'],
                            1,
                        ]
                    ],
                ]
            ]
        ];

        $this->lookup = new ArrayRefLookup($arr);
    }

    /**
     * Test allof with two elements
     */
    public function testResolveOneLevel() {
        $schema = new Schema(['$ref' => '#/components/schemas/Cat'], $this->lookup);
        $valid = $schema->validate(['name' => 'mauzi', 'likes' => 'milk']);
        $this->assertSame(['name' => 'mauzi', 'likes' => 'milk'], $valid);
    }

    /**
     * Test allof with two elements while one of those has another allof
     */
    public function testResolveTwoLevel() {
        $schema = new Schema(['$ref' => '#/components/schemas/Puppy'], $this->lookup);
        $valid = $schema->validate(['name' => 'roger', 'isGoodBoy' => true, 'canRun' => false]);
        $this->assertSame(['name' => 'roger', 'isGoodBoy' => true, 'canRun' => false], $valid);
    }

    /**
     * Fail if an invalid value is provided
     */
    public function testInvalidProperty() {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches("/Unexpected property: notExists/");
        $schema = new Schema(['$ref' => '#/components/schemas/Puppy'], $this->lookup);
        $schema->setFlags(Schema::VALIDATE_EXTRA_PROPERTY_EXCEPTION);
        $schema->validate(['name' => 'roger', 'isGoodBoy' => true, 'notExists' => true]);
    }

    /**
     * Allof members must be array
     */
    public function testInvalidAllOf() {
        $this->expectException(ParseException::class);
        $schema = new Schema(['$ref' => '#/components/schemas/Invalid1'], $this->lookup);
        $valid = $schema->validate(['type' => 'Invalid1']);
    }
}
