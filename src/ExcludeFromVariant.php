<?php
/**
 * @author Adam Charron <adam@vanillaforums.com>
 * @copyright 2009-2024 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema;

/**
 * Attribute to exclude a property from specific schema variants.
 *
 * By default, all public properties are included in all schema variants.
 * Use this attribute to exclude a property from one or more specific variants.
 *
 * This attribute is repeatable, allowing you to exclude from multiple variants:
 *
 * ```php
 * #[ExcludeFromVariant(SchemaVariant::Fragment)]
 * #[ExcludeFromVariant(SchemaVariant::Mutable)]
 * public string $body;
 * ```
 *
 * Or you can pass multiple variants in a single attribute:
 *
 * ```php
 * #[ExcludeFromVariant(SchemaVariant::Fragment, SchemaVariant::Mutable)]
 * public string $body;
 * ```
 *
 * You can also use custom variant enums:
 *
 * ```php
 * enum MyVariant: string {
 *     case Public = 'public';
 *     case Admin = 'admin';
 * }
 *
 * #[ExcludeFromVariant(MyVariant::Public)]
 * public string $adminNotes;
 * ```
 *
 * Common use cases:
 * - Exclude large content fields from Fragment schema
 * - Exclude system-managed fields (createdAt, updatedAt) from Mutable schema
 * - Exclude read-only computed fields from Mutable/Create schemas
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::IS_REPEATABLE)]
class ExcludeFromVariant {
    /**
     * @var \BackedEnum[]
     */
    private array $variants;

    /**
     * @param \BackedEnum ...$variants The schema variants to exclude this property from.
     */
    public function __construct(\BackedEnum ...$variants) {
        $this->variants = $variants;
    }

    /**
     * Get the variants this property should be excluded from.
     *
     * @return \BackedEnum[]
     */
    public function getVariants(): array {
        return $this->variants;
    }

    /**
     * Check if the property should be excluded from the given variant.
     *
     * @param \BackedEnum $variant The variant to check.
     * @return bool True if the property should be excluded from this variant.
     */
    public function excludesVariant(\BackedEnum $variant): bool {
        foreach ($this->variants as $excluded) {
            if ($excluded::class === $variant::class && $excluded->value === $variant->value) {
                return true;
            }
        }
        return false;
    }
}
