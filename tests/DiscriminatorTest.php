<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema\Tests;

use Garden\Schema\ArrayRefLookup;
use Garden\Schema\Schema;
use PHPUnit\Framework\TestCase;

/**
 * Tests the discriminator property.
 */
class DiscriminatorTest extends TestCase {

    /**
     * @var Schema
     */
    private $schema;

    public function setUp() {
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
     *
     * @expectedException \Garden\Schema\ValidationException
     * @expectedExceptionMessageRegExp `type: "Foo" is not a valid option.`
     */
    public function testDiscriminatorTypeNotFound() {
        $valid = $this->schema->validate(['type' => 'Foo']);
    }

    /**
     * A discriminators should fail if the discriminate to themselves.
     *
     * @expectedException \Garden\Schema\ValidationException
     * @expectedExceptionMessageRegExp `type: "Pet" is not a valid option.`
     */
    public function testDiscriminatorCyclicalRef() {
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
     * A discriminators should fail if the discriminate to themselves.
     *
     * @expectedException \Garden\Schema\ValidationException
     * @expectedExceptionMessageRegExp `subtype: "Pet" is not a valid option.`
     */
    public function testDiscriminatorInfiniteRecursion() {
        $valid = $this->schema->validate(['type' => 'Bird', 'subtype' => 'Pet']);
    }

    /**
     * A discriminators should fail if the discriminate to themselves.
     *
     * @expectedException \Garden\Schema\ValidationException
     * @expectedExceptionMessageRegExp `type: The value is not a valid string.`
     */
    public function testDiscriminatorTypeError() {
        $valid = $this->schema->validate(['type' => ['hey!!!']]);
    }

    /**
     * A discriminators should fail if the discriminate to themselves.
     *
     * @expectedException \Garden\Schema\ValidationException
     * @expectedExceptionMessageRegExp `type: type is required.`
     */
    public function testEmptyDiscriminator() {
        $valid = $this->schema->validate([]);
    }

    /**
     * A discriminators should fail if the discriminate to themselves.
     *
     * @expectedException \Garden\Schema\ValidationException
     * @expectedExceptionMessageRegExp `"foo" is not a valid object.`
     */
    public function testDiscriminatorNonObject() {
        $valid = $this->schema->validate('foo');
    }

    /**
     * The property name for a discriminator must be a string.
     *
     * @expectedException \Garden\Schema\ParseException
     */
    public function testInvalidDiscriminator1() {
        $valid = $this->schema->validate(['type' => 'Invalid1']);
    }
}