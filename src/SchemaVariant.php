<?php
/**
 * @author Adam Charron <adam@vanillaforums.com>
 * @copyright 2009-2024 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema;

/**
 * Enum representing different schema variants for an Entity.
 *
 * Schema variants allow a single Entity class to generate multiple schemas
 * for different API use cases:
 *
 * - **Full**: Complete entity with all properties (default)
 * - **Fragment**: Reduced version for lists, omitting large/detail fields
 * - **Mutable**: Fields that can be modified by consumers (for PATCH requests)
 * - **Create**: Includes create-only fields (for POST requests)
 * - **Internal**: For internal/system use, may include sensitive fields not exposed via API
 *
 * You can also define your own variant enums by creating a BackedEnum and using it
 * with the ExcludeFromVariant and IncludeOnlyInVariant attributes.
 */
enum SchemaVariant: string {
    /**
     * Full schema with all properties.
     * Used for single-item GET responses.
     */
    case Full = 'full';

    /**
     * Fragment schema with reduced properties.
     * Used for list responses, embedded references, omitting large strings and details.
     */
    case Fragment = 'fragment';

    /**
     * Mutable schema with only user-modifiable fields.
     * Used for PATCH requests. Excludes system-managed fields like timestamps.
     */
    case Mutable = 'mutable';

    /**
     * Create schema with create-only fields included.
     * Used for POST requests. May include fields not present in other variants.
     */
    case Create = 'create';

    /**
     * Internal schema for system/internal use.
     * May include sensitive fields not exposed via public API.
     */
    case Internal = 'internal';
}
