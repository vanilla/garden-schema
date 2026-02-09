<?php
/**
 * @author Adam Charron <adam@vanillaforums.com>
 * @copyright 2009-2024 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema;

/**
 * Enum representing the format of entity field names.
 *
 * Used with Entity::convertFieldName() and Entity::convertFieldNames()
 * to convert between canonical property names and their primary alternative names.
 *
 * - **Canonical**: The PHP property name as declared on the Entity class.
 * - **PrimaryAltName**: The primary alternative name from PropertyAltNames attribute.
 *
 * Example:
 * ```php
 * // Given: #[PropertyAltNames('user_name')] public string $name;
 * MyEntity::convertFieldName('name', EntityFieldFormat::PrimaryAltName);  // 'user_name'
 * MyEntity::convertFieldName('user_name', EntityFieldFormat::Canonical);  // 'name'
 * ```
 */
enum EntityFieldFormat: string {
    /**
     * The canonical PHP property name as declared on the Entity class.
     */
    case Canonical = 'canonical';

    /**
     * The primary alternative name from the PropertyAltNames attribute.
     */
    case PrimaryAltName = 'primaryAltName';
}
