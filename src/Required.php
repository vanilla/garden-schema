<?php
/**
 * @author Adam Charron <adam.c@vanillaforums.com>
 * @copyright 2009-2024 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema;

/**
 * Attribute to mark a property as required in the schema.
 *
 * This is useful when you have a nullable property that should still be required
 * in the schema validation. For example, a property that accepts null as a valid
 * value but must always be explicitly provided.
 *
 * Example:
 * ```
 * #[Required]
 * public ?string $name;  // Nullable in PHP, but required in schema validation
 * ```
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Required extends PropertySchema {
    public function __construct() {
        parent::__construct([]);
    }
}
