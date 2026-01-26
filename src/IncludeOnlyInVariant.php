<?php
/**
 * @author Adam Charron <adam@vanillaforums.com>
 * @copyright 2009-2024 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema;

/**
 * Attribute to include a property only in specific schema variants.
 *
 * Properties marked with this attribute will ONLY be included in the specified variants
 * and will be excluded from all other variants (including Full by default).
 *
 * This is the inverse of ExcludeFromVariant and is useful for:
 * - Create-only fields (e.g., initial password, invite code)
 * - Variant-specific computed fields
 *
 * Example:
 *
 * ```php
 * // This property only appears in Create schema
 * #[IncludeOnlyInVariant(SchemaVariant::Create)]
 * public ?string $initialPassword;
 *
 * // This property appears in both Create and Full schemas
 * #[IncludeOnlyInVariant(SchemaVariant::Create, SchemaVariant::Full)]
 * public ?string $inviteCode;
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
 * #[IncludeOnlyInVariant(MyVariant::Admin)]
 * public string $adminOnlyField;
 * ```
 *
 * Note: This attribute cannot be combined with ExcludeFromVariant on the same property.
 * If both are present, IncludeOnlyInVariant takes precedence.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class IncludeOnlyInVariant {
    /**
     * @var \BackedEnum[]
     */
    private array $variants;

    /**
     * @param \BackedEnum ...$variants The schema variants where this property should be included.
     */
    public function __construct(\BackedEnum ...$variants) {
        $this->variants = $variants;
    }

    /**
     * Get the variants where this property should be included.
     *
     * @return \BackedEnum[]
     */
    public function getVariants(): array {
        return $this->variants;
    }

    /**
     * Check if the property should be included in the given variant.
     *
     * @param \BackedEnum $variant The variant to check.
     * @return bool True if the property should be included in this variant.
     */
    public function includesVariant(\BackedEnum $variant): bool {
        foreach ($this->variants as $included) {
            if ($included::class === $variant::class && $included->value === $variant->value) {
                return true;
            }
        }
        return false;
    }
}
