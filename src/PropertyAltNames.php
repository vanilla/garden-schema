<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema;

use Garden\Utils\ArrayUtils;

/**
 * Attribute to specify alternative property names that map to a property.
 *
 * When validating, if the main property is not present, the alternative names
 * are checked in order and the first match is used as the property value.
 *
 * The first parameter accepts either a single string or an array of strings:
 * - Single string: `#[PropertyAltNames('user_name')]`
 * - Array of strings: `#[PropertyAltNames(['user_name', 'userName'], primaryAltName: 'user_name')]`
 *
 * When multiple alt names are provided, you must specify a `primaryAltName` parameter.
 * This primary alt name is used when serializing back to the alternative format via `toAltArray()`.
 *
 * When useDotNotation is enabled (default), alt names containing dots are treated
 * as nested paths. For example, 'attributes.theProperty' will look for
 * $data['attributes']['theProperty'].
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class PropertyAltNames {
    /**
     * @var string[]
     */
    private array $altNames;

    /**
     * @var bool Whether to treat dots in alt names as path separators for nested lookup.
     */
    private bool $useDotNotation;

    /**
     * @var string|null The primary alt name to use when serializing back to alt format.
     */
    private ?string $primaryAltName;

    /**
     * @param string|string[] $altNames Alternative property name(s) to check. Can be a single string or array of strings.
     * @param bool $useDotNotation Whether to treat dots as path separators for nested lookup. Default true.
     * @param string|null $primaryAltName The primary alt name to use when serializing back to alt format.
     *                                    Required when multiple alt names are provided.
     */
    public function __construct(string|array $altNames = [], bool $useDotNotation = true, ?string $primaryAltName = null) {
        $this->altNames = is_string($altNames) ? [$altNames] : $altNames;
        $this->useDotNotation = $useDotNotation;

        // Validate: if multiple alt names, primaryAltName must be specified
        if (count($this->altNames) > 1 && $primaryAltName === null) {
            throw new \InvalidArgumentException(
                'When multiple alt names are provided, primaryAltName must be specified.'
            );
        }

        // If single alt name and no primary specified, use the single alt name as primary
        if (count($this->altNames) === 1 && $primaryAltName === null) {
            $this->primaryAltName = $this->altNames[0];
        } else {
            $this->primaryAltName = $primaryAltName;
        }

        // Validate that primaryAltName is in the alt names list
        if ($this->primaryAltName !== null && !in_array($this->primaryAltName, $this->altNames, true)) {
            throw new \InvalidArgumentException(
                "primaryAltName '{$this->primaryAltName}' must be one of the provided alt names."
            );
        }
    }

    /**
     * Get the alternative property names.
     *
     * @return string[]
     */
    public function getAltNames(): array {
        return $this->altNames;
    }

    /**
     * Check if dot notation is enabled for nested path lookup.
     *
     * @return bool
     */
    public function useDotNotation(): bool {
        return $this->useDotNotation;
    }

    /**
     * Get the primary alt name to use for serialization.
     *
     * @return string|null
     */
    public function getPrimaryAltName(): ?string {
        return $this->primaryAltName;
    }

    /**
     * Try to resolve a value from the data array using the alt names.
     *
     * Iterates through alt names in order and returns the first match.
     * For non-dot-notation matches, the matched alt name key is removed from $data.
     * For dot-notation matches, the nested value is read but the source is left intact.
     *
     * @param array &$data The data to search in. May be modified if a non-dot-notation match is found.
     * @param mixed $default Default to return if no alt name matches.
     * @return mixed The resolved value, or $default if not found.
     */
    public function resolveFromData(array &$data, mixed $default = null): mixed {
        foreach ($this->altNames as $altName) {
            if ($this->useDotNotation && str_contains($altName, '.')) {
                $sentinel = new \stdClass();
                $value = ArrayUtils::getByPath($altName, $data, $sentinel);
                if ($value !== $sentinel) {
                    return $value;
                }
            } elseif (array_key_exists($altName, $data)) {
                $value = $data[$altName];
                return $value;
            }
        }
        return $default;
    }
}
