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
 * Tests the discriminator property.
 */
class DiscriminatorTest extends TestCase {

    /**
     * @var Schema
     */
    private $schema;

    public function setUp(): void {
        parent::setUp();

        $arr = [
            'components' => [
                'schemas' => [
                    'Pet' => [
                        'discriminator' => [
                            'propertyName' => 'type',
                            'mapping' => [
                                'Fido' => 'Dog',
                                'Mittens' => '#/components/schemas/Cat',
                            ]
                        ]
                    ],
                    'Cat' => [
                        'type' => 'object',
                        'properties' => [
                            'type' => [
                                'type' => 'string',
                            ],
                            'likes' => [
                                'type' => 'string',
                                'enum' => ['milk', 'purring'],
                            ]
                        ]
                    ],
                    'Dog' => [
                        'type' => 'object',
                        'properties' => [
                            'type' => [
                                'type' => 'string',
                            ],
                            'isGoodBoy' => [
                                'type' => 'boolean',
                                'default' => true,
                            ]
                        ]
                    ],
                    'Bird' => [
                        'oneOf' => [
                            [
                                '$ref' => '#/components/schemas/Penguin',
                            ],
                            [
                                '$ref' => '#/components/schemas/Parrot',
                            ],
                            [
                                '$ref' => '#/components/schemas/Pet',
                            ],
                        ],
                        'discriminator' => [
                            'propertyName' => 'subtype',
                        ]
                    ],
                    'Penguin' => [
                        'type' => 'object',
                        'properties' => [
                            'type' => [
                                'type' => 'string',
                            ],
                            'subtype' => [
                                'type' => 'string',
                            ],
                            'movement' => [
                                'type' => 'string',
                                'default' => 'swims',
                            ],
                        ],
                    ],
                    'Parrot' => [
                        'type' => 'object',
                        'properties' => [
                            'type' => [
                                'type' => 'string',
                            ],
                            'subtype' => [
                                'type' => 'string',
                            ],
                            'movement' => [
                                'type' => 'string',
                                'default' => 'flies',
                            ],
                        ],
                    ],
                    'Invalid1' => [
                        'discriminator' => [
                            'propertyName' => [1, 2, 3],
                            'mapping' => [
                                'Fido' => 'Dog',
                                'Mittens' => '#/components/schemas/Cat',
                            ]
                        ]
                    ],
                ]
            ]
        ];

        $lookup = new ArrayRefLookup($arr);

        $this->schema = new Schema(['$ref' => '#/components/schemas/Pet'], $lookup);
    }

    /**
     * Test a discriminator that maps to a reference.
     */
    public function testDiscriminatorRefMapping() {
        $valid = $this->schema->validate(['type' => 'Mittens', 'likes' => 'milk', 'isGoodBoy' => true]);

        $this->assertSame(['type' => 'Mittens', 'likes' => 'milk'], $valid);
    }

    /**
     * Test a discriminator that maps to an alias.
     */
    public function testDiscriminatorAliasMapping() {
        $valid = $this->schema->validate(['type' => 'Fido']);

        $this->assertSame(['type' => 'Fido', 'isGoodBoy' => true], $valid);
    }

    /**
     * Test a discriminator default mapping.
     */
    public function testDiscriminatorDefaultMapping() {
        $valid = $this->schema->validate(['type' => 'Cat', 'likes' => 'milk', 'isGoodBoy' => true]);

        $this->assertSame(['type' => 'Cat', 'likes' => 'milk'], $valid);
    }

    /**
     * A discriminator value that is not found should make validation fail.
     */
    public function testDiscriminatorTypeNotFound() {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("\"Foo\" is not a valid option.");
        $valid = $this->schema->validate(['type' => 'Foo']);
    }

    /**
     * A discriminator should fail if it refers to its own schema, creating a circular reference.
     */
    public function testDiscriminatorCyclicalRef() {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("\"Pet\" is not a valid option.");
        $valid = $this->schema->validate(['type' => 'Pet']);
    }

    /**
     * A schema found through a discriminator should itself be allowed to have a recursive discriminator.
     */
    public function testDiscriminatorRecursion() {
        $valid = $this->schema->validate(['type' => 'Bird', 'subtype' => 'Penguin']);

        $this->assertSame(['type' => 'Bird', 'subtype' => 'Penguin', 'movement' => 'swims'], $valid);
    }

    /**
     * A discriminators should fail if they discriminate to themselves.
     */
    public function testDiscriminatorInfiniteRecursion() {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("\"Pet\" is not a valid option.");
        $valid = $this->schema->validate(['type' => 'Bird', 'subtype' => 'Pet']);
    }

    /**
     * A discriminators should fail if they discriminate to themselves.
     */
    public function testDiscriminatorTypeError() {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("The value is not a valid string.");
        $valid = $this->schema->validate(['type' => ['hey!!!']]);
    }

    /**
     * A discriminators should fail if the discriminate to themselves.
     */
    public function testEmptyDiscriminator() {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("type is required.");
        $valid = $this->schema->validate([]);
    }

    /**
     * A discriminators should fail if they discriminate to themselves.
     */
    public function testDiscriminatorNonObject() {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("\"foo\" is not a valid object.");
        $valid = $this->schema->validate('foo');
    }

    /**
     * The property name for a discriminator must be a string.
     */
    public function testInvalidDiscriminator1() {
        $this->expectException(ParseException::class);
        $valid = $this->schema->validate(['type' => 'Invalid1']);
    }

    /**
     * A discriminator has to respect the `oneOf` validation.
     */
    public function testOneOfRef() {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('"Dog" is not a valid option.');
        $valid = $this->schema->validate(['type' => 'Bird', 'subtype' => 'Dog']);
    }
}
