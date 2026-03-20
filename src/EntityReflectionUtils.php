<?php
/**
 * @author Adam Charron <adam.c@vanillaforums.com>
 * @copyright 2009-2026 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema;

use ReflectionClass;
use ReflectionProperty;

/**
 * Reflection utility helpers for entity processing.
 */
class EntityReflectionUtils {

    /**
     * Get reflection properties sorted by inheritance declaration order.
     *
     * The returned properties mirror ReflectionClass::getProperties($filters), but are
     * re-ordered so properties introduced by base classes come first.
     *
     * For redeclared properties, ordering is based on the first class in the inheritance
     * chain that introduced that property name.
     *
     * @param ReflectionClass $reflectedClass
     * @param int|null $filters ReflectionProperty filter mask.
     * @return ReflectionProperty[]
     */
    public static function getSortedProperties(ReflectionClass $reflectedClass, ?int $filters): array {
        $properties = $filters === null
            ? $reflectedClass->getProperties()
            : $reflectedClass->getProperties($filters);

        $inheritanceChain = [];
        for ($cursor = $reflectedClass; $cursor !== false; $cursor = $cursor->getParentClass()) {
            $inheritanceChain[] = $cursor;
        }
        $inheritanceChain = array_reverse($inheritanceChain);

        $classDepth = [];
        $propertyIntroductionOrder = [];
        $order = 0;

        foreach ($inheritanceChain as $depth => $class) {
            $classDepth[$class->getName()] = $depth;
            $classProperties = $filters === null
                ? $class->getProperties()
                : $class->getProperties($filters);

            foreach ($classProperties as $property) {
                if ($property->getDeclaringClass()->getName() !== $class->getName()) {
                    continue;
                }
                $propertyName = $property->getName();
                if (!array_key_exists($propertyName, $propertyIntroductionOrder)) {
                    $propertyIntroductionOrder[$propertyName] = $order++;
                }
            }
        }

        $sortableProperties = [];
        foreach ($properties as $index => $property) {
            $declaringClassName = $property->getDeclaringClass()->getName();
            $sortableProperties[] = [
                "property" => $property,
                "firstSeenOrder" => $propertyIntroductionOrder[$property->getName()] ?? PHP_INT_MAX,
                "declaringClassDepth" => $classDepth[$declaringClassName] ?? PHP_INT_MAX,
                "originalIndex" => $index,
            ];
        }

        usort($sortableProperties, function (array $a, array $b): int {
            $comparison = $a["firstSeenOrder"] <=> $b["firstSeenOrder"];
            if ($comparison !== 0) {
                return $comparison;
            }

            $comparison = $a["declaringClassDepth"] <=> $b["declaringClassDepth"];
            if ($comparison !== 0) {
                return $comparison;
            }

            return $a["originalIndex"] <=> $b["originalIndex"];
        });

        return array_map(fn(array $item): ReflectionProperty => $item["property"], $sortableProperties);
    }
}
