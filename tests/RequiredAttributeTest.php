<?php

namespace Garden\Schema\Tests;

use Garden\Schema\Entity;
use Garden\Schema\Required;
use Garden\Schema\ValidationException;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Required attribute.
 */
class RequiredAttributeTest extends TestCase {

    public function testNullablePropertyWithRequiredAttribute(): void {
        $schema = RequiredNullableEntity::getSchema();
        $schemaArray = $schema->getSchemaArray();

        // The nullable property with #[Required] should be in required list
        $this->assertContains('requiredNullable', $schemaArray['required']);

        // The regular nullable property should NOT be in required list
        $this->assertNotContains('optionalNullable', $schemaArray['required']);
    }

    public function testRequiredNullablePropertyMustBeProvided(): void {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('requiredNullable is required');

        // Should fail because requiredNullable is required but not provided
        RequiredNullableEntity::from([
            'name' => 'Test',
        ]);
    }

    public function testRequiredNullablePropertyAcceptsNull(): void {
        // Should succeed because requiredNullable is provided (as null)
        $entity = RequiredNullableEntity::from([
            'name' => 'Test',
            'requiredNullable' => null,
        ]);

        $this->assertSame('Test', $entity->name);
        $this->assertNull($entity->requiredNullable);
    }

    public function testRequiredNullablePropertyAcceptsValue(): void {
        $entity = RequiredNullableEntity::from([
            'name' => 'Test',
            'requiredNullable' => 'Hello',
        ]);

        $this->assertSame('Test', $entity->name);
        $this->assertSame('Hello', $entity->requiredNullable);
    }

    public function testOptionalNullablePropertyCanBeOmitted(): void {
        $entity = RequiredNullableEntity::from([
            'name' => 'Test',
            'requiredNullable' => 'Value',
        ]);

        $this->assertSame('Test', $entity->name);
        $this->assertNull($entity->optionalNullable);
    }

    public function testNonNullablePropertyStillRequired(): void {
        $schema = RequiredNullableEntity::getSchema();
        $schemaArray = $schema->getSchemaArray();

        // Non-nullable properties should still be required
        $this->assertContains('name', $schemaArray['required']);
    }
}

/**
 * Test entity with Required attribute on a nullable property.
 */
class RequiredNullableEntity extends Entity {
    public string $name;

    /**
     * This property is nullable but required - consumers must explicitly provide a value (including null).
     */
    #[Required]
    public ?string $requiredNullable;

    /**
     * This property is nullable and optional - can be omitted entirely.
     */
    public ?string $optionalNullable = null;
}
