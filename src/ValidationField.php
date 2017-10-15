<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2017 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema;

/**
 * A parameters class for field validation.
 */
class ValidationField {
    /**
     * @var array|Schema
     */
    private $field;

    /**
     * @var Validation
     */
    private $validation;

    /**
     * @var string
     */
    private $name;

    /**
     * @var bool
     */
    private $sparse;

    /**
     * Construct a new {@link ValidationField} object.
     *
     * @param Validation $validation The validation object that contains errors.
     * @param array|Schema $field The field definition.
     * @param string $name The path to the field.
     * @param bool $sparse Whether this is a sparse validation or not.
     */
    public function __construct(Validation $validation, $field, $name, $sparse = false) {
        $this->field = $field;
        $this->validation = $validation;
        $this->name = $name;
        $this->sparse = $sparse;
    }

    /**
     * Add a validation error.
     *
     * @param string $error The message code.
     * @param int|array $options An array of additional information to add to the error entry or a numeric error code.
     * @return $this
     * @see Validation::addError()
     */
    public function addError($error, $options = []) {
        $this->validation->addError($this->getName(), $error, $options);
        return $this;
    }

    /**
     * Add an invalid type error.
     *
     * @param string $type The type that was checked.
     * @return $this
     */
    public function addTypeError($type = '') {
        $type = $type ?: $this->getType();

        $this->validation->addError(
            $this->getName(),
            'invalid',
            [
                'type' => $type,
                'messageCode' => '{field} is not a valid {type}.',
                'status' => 422
            ]
        );

        return $this;
    }

    /**
     * Check whether or not this field is has errors.
     *
     * @return bool Returns true if the field has no errors, false otherwise.
     */
    public function isValid() {
        return $this->getValidation()->isValidField($this->getName());
    }

    /**
     * Merge a validation object to this one.
     *
     * @param Validation $validation The validation object to merge.
     * @return $this
     */
    public function merge(Validation $validation) {
        $this->getValidation()->merge($validation, $this->getName());
        return $this;
    }

    /**
     * Get the field.
     *
     * @return array|Schema Returns the field.
     */
    public function getField() {
        return $this->field;
    }

    /**
     * Set the field.
     *
     * This method is only meant to be called from within the schema class.
     *
     * @param array|Schema $field The new field.
     * @return $this
     */
    public function setField($field) {
        $this->field = $field;
        return $this;
    }

    /**
     * Get the validation.
     *
     * @return Validation Returns the validation.
     */
    public function getValidation() {
        return $this->validation;
    }

    /**
     * Get the name.
     *
     * @return string Returns the name.
     */
    public function getName() {
        return $this->name;
    }

    /**
     * Set the name.
     *
     * This method is only meant to be called from within the schema class.
     *
     * @param string $name The new name.
     * @return $this
     */
    public function setName($name) {
        $this->name = $name;
        return $this;
    }

    /**
     * Get the field type.
     *
     * @return string|string[]|null Returns a type string, array of type strings, or null if there isn't one.
     */
    public function getType() {
        return isset($this->field['type']) ? $this->field['type'] : null;
    }

    /**
     * Whether or not the field has a given type.
     *
     * @param string $type The single type to test.
     * @return bool Returns **true** if the field has the given type or **false** otherwise.
     */
    public function hasType($type) {
        return in_array($type, (array)$this->getType());
    }

    /**
     * Get a value fom the field.
     *
     * @param string $key The key to look at.
     * @param mixed $default The default to return if the key isn't found.
     * @return mixed Returns a value or the default.
     */
    public function val($key, $default = null) {
        return isset($this->field[$key]) ? $this->field[$key] : $default;
    }

    /**
     * Whether or not the field has a value.
     *
     * @param string $key The key to look at.
     * @return bool Returns **true** if the field has a key or **false** otherwise.
     */
    public function hasVal($key) {
        return array_key_exists($key, $this->field);
    }

    /**
     * Get the error count for this field.
     */
    public function getErrorCount() {
        return $this->getValidation()->getErrorCount($this->getName());
    }

    /**
     * Whether or not this is a sparse validation..
     *
     * @return bool Returns **true** if this is a sparse validation or **false** otherwise.
     */
    public function isSparse() {
        return $this->sparse;
    }
}
