<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema;

/**
 * Attribute to exclude a property from schema generation and validation.
 *
 * Properties marked with this attribute will not be included in the generated schema,
 * will not be validated, and will not be populated by Entity::from() or Entity::fromValidated().
 * This is useful for computed properties, caches, or internal state.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class ExcludeFromSchema {
}
