<?php
/**
 * @author Adam Charron <adam.c@vanillaforums.com>
 * @copyright 2009-2026 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema;

/**
 * Interface for exclude from variant attributes.
 */
interface ExcludeFromVariantInterface {

    /**
     * Get the variants this property should be excluded from.
     */
    public function getVariants(): array;

    /**
     * Check if the property should be excluded from the given variant.
     *
     * @param \BackedEnum $variant The variant to check.
     * @return bool True if the property should be excluded from this variant.
     */
    public function excludesVariant(\BackedEnum $variant): bool;
}
