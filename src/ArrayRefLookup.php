<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema;

/**
 * A schema `$ref` lookup that searches arrays.
 *
 * @see https://swagger.io/docs/specification/using-ref/
 */
class ArrayRefLookup {
    /**
     * @var array|\ArrayAccess
     */
    private $array;

    /**
     * ArrayRefLookup constructor.
     *
     * @param array|\ArrayAccess $array The array that is searched.
     */
    public function __construct($array) {
        $this->array = $array;
    }

    /**
     * Lookup a schema based on a JSON ref.
     *
     * @param string $ref A valid JSON ref.
     * @return mixed|null Returns the value at the reference or **null** if the reference isn't found.
     * @see https://swagger.io/docs/specification/using-ref/
     */
    public function __invoke(string $ref) {
        $urlParts = parse_url($ref);
        if (!empty($urlParts['host']) || !empty($urlParts['path'])) {
            throw new \InvalidArgumentException("Only local schema references are supported. ($ref)", 400);
        }
        $fragment = $urlParts['fragment'] ?? '';
        if (strlen($fragment) === 0 || $fragment[0] !== '/') {
            throw new \InvalidArgumentException("Relative schema references are not supported. ($ref)", 400);
        }

        if ($fragment === '/') {
            return $this->array;
        }
        $parts = Schema::explodeRef(substr($fragment, 1));

        $value = $this->array;
        foreach ($parts as $key) {
            if (!is_string($value) && isset($value[$key])) {
                $value = $value[$key];
            } else {
                return null;
            }
        }
        return $value;
    }

    /**
     * Get the array.
     *
     * @return array|\ArrayAccess Returns the array.
     */
    public function getArray() {
        return $this->array;
    }

    /**
     * Set the array.
     *
     * @param array|\ArrayAccess $array
     * @return $this
     */
    public function setArray($array) {
        $this->array = $array;
        return $this;
    }
}
