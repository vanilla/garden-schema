<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema;

/**
 * Attribute to specify alternative property names that map to a property.
 *
 * When validating, if the main property is not present, the alternative names
 * are checked in order and the first match is used as the property value.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class PropertyAltNames {
    /**
     * @var string[]
     */
    private array $altNames;

    /**
     * @param string ...$altNames Alternative property names to check.
     */
    public function __construct(string ...$altNames) {
        $this->altNames = $altNames;
    }

    /**
     * Get the alternative property names.
     *
     * @return string[]
     */
    public function getAltNames(): array {
        return $this->altNames;
    }
}
