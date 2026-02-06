<?php
/**
 * @author Adam Charron <adam.c@vanillaforums.com>
 * @copyright 2009-2026 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema;

/**
 * Interface for property schemas.
 */
interface PropertySchemaInterface {

    /**
     * Get the schema for the property.
     */
    public function getSchema(): Schema;
}
