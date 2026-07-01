<?php

namespace Garden\Schema\Tests;

use Garden\Schema\EntityReflectionUtils;
use Garden\Schema\Tests\Fixtures\ReflectionUtilsChildClass;
use Garden\Schema\Tests\Fixtures\ReflectionUtilsEntityC;
use Garden\Schema\Tests\Fixtures\ReflectionUtilsSchemaChildEntity;
use Garden\Schema\Tests\Fixtures\ReflectionUtilsSecondClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

/**
 * Tests for EntityReflectionUtils.
 */
class EntityReflectionUtilsTest extends TestCase {
    /**
     * Properties are sorted parent-first, but redeclared properties use the most specific subclass position.
     */
    public function testGetSortedPropertiesSortsByInheritanceChainWithRedeclaredProperty(): void {
        $reflectedClass = new ReflectionClass(ReflectionUtilsChildClass::class);

        $properties = EntityReflectionUtils::getSortedProperties($reflectedClass, null);
        $names = array_map(fn(ReflectionProperty $property) => $property->getName(), $properties);

        $this->assertSame(
            ["baseProtectedProp", "secondClassProp", "secondProtectedProp", "baseClassProp", "thirdClassProp"],
            $names,
        );
    }

    /**
     * The utility applies the same reflection filters as ReflectionClass::getProperties().
     */
    public function testGetSortedPropertiesRespectsFilters(): void {
        $reflectedClass = new ReflectionClass(ReflectionUtilsChildClass::class);

        $properties = EntityReflectionUtils::getSortedProperties($reflectedClass, ReflectionProperty::IS_PUBLIC);
        $names = array_map(fn(ReflectionProperty $property) => $property->getName(), $properties);

        $this->assertSame(["secondClassProp", "baseClassProp", "thirdClassProp"], $names);
    }

    /**
     * Protected property sorting is also parent-first.
     */
    public function testGetSortedPropertiesWithProtectedFilter(): void {
        $reflectedClass = new ReflectionClass(ReflectionUtilsSecondClass::class);

        $properties = EntityReflectionUtils::getSortedProperties($reflectedClass, ReflectionProperty::IS_PROTECTED);
        $names = array_map(fn(ReflectionProperty $property) => $property->getName(), $properties);

        $this->assertSame(["baseProtectedProp", "secondProtectedProp"], $names);
    }

    /**
     * Entity schema generation starts from parent-first ordering before applying SchemaOrder.
     */
    public function testEntityBuildSchemaUsesInheritanceOrderingBeforeSchemaOrder(): void {
        $schema = ReflectionUtilsSchemaChildEntity::getSchema();
        $propertyNames = array_keys($schema->getSchemaArray()["properties"]);

        $this->assertSame(["schemaOrdered", "parentA", "parentB", "childA"], $propertyNames);
    }

    /**
     * Redeclared properties in each subclass use that subclass's declaration position.
     */
    public function testGetSortedPropertiesUsesMostSpecificRedeclarationOrder(): void {
        $reflectedClass = new ReflectionClass(ReflectionUtilsEntityC::class);

        $properties = EntityReflectionUtils::getSortedProperties($reflectedClass, ReflectionProperty::IS_PUBLIC);
        $names = array_map(fn(ReflectionProperty $property) => $property->getName(), $properties);

        $this->assertSame(["propA", "propB", "propC", "propD"], $names);
    }

    /**
     * Entity schema property order follows the same redeclaration-aware inheritance ordering.
     */
    public function testEntityBuildSchemaUsesMostSpecificRedeclarationOrder(): void {
        $schema = ReflectionUtilsEntityC::getSchema();
        $propertyNames = array_keys($schema->getSchemaArray()["properties"]);

        $this->assertSame(["propA", "propB", "propC", "propD"], $propertyNames);
    }
}
