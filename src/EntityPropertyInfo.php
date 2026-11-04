<?php
/**
 * @copyright 2009-2026 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema;

/**
 * Reflection facts about one property of an entity class, collected once per class.
 *
 * Hydrating or setting a property reads this instead of reflecting on the property again for every row.
 */
final class EntityPropertyInfo {
    /** Untyped or builtin type. Arrays of nested entities are still resolved from the schema. */
    public const KIND_PLAIN = "plain";

    /** Typed as the {@link EntityInterface} interface itself. */
    public const KIND_ENTITY_INTERFACE = "entityInterface";

    /** Typed as a class that implements {@link EntityInterface}. */
    public const KIND_ENTITY = "entity";

    /** Typed as a backed enum. */
    public const KIND_ENUM = "enum";

    /** Typed as an `ArrayObject`. */
    public const KIND_ARRAY_OBJECT = "arrayObject";

    /** Typed as a `DateTimeImmutable`. */
    public const KIND_DATE_TIME = "dateTime";

    /** Typed as a UUID. */
    public const KIND_UUID = "uuid";

    /** Typed as some other class, which is assigned as is. */
    public const KIND_OTHER = "other";

    /**
     * @param string $name The property name.
     * @param bool $isPublic Whether the property is public.
     * @param bool $isExcluded Whether the property has `ExcludeFromSchema`.
     * @param string|null $typeName The declared type, or null if untyped.
     * @param bool $allowsNull Whether the declared type allows null.
     * @param bool $hasDefault Whether the property declares a default value.
     * @param bool $hasEntityDefault Whether the declared type is an {@link EntityDefaultInterface}.
     * @param string $kind One of the `KIND_*` constants.
     */
    public function __construct(
        public readonly string $name,
        public readonly bool $isPublic,
        public readonly bool $isExcluded,
        public readonly ?string $typeName,
        public readonly bool $allowsNull,
        public readonly bool $hasDefault,
        public readonly bool $hasEntityDefault,
        public readonly string $kind,
    ) {
    }
}
