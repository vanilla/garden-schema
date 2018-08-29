<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema;

/**
 * A parameters class for field validation.
 *
 * This is an internal class and may change in the future.
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
     * @var array
     */
    private $options;

    /**
     * @var string
     */
    private $schemaPath;

    /**
     * Construct a new {@link ValidationField} object.
     *
     * @param Validation $validation The validation object that contains errors.
     * @param array|Schema $field The field definition.
     * @param string $name The path to the field.
     * @param string $schemaPath The path to the field within the parent schema.
     *
     * - **sparse**: Whether or not this is a sparse validation.
     * @param array $options Validation options.
     */
    public function __construct(Validation $validation, $field, string $name, string $schemaPath, array $options = []) {
        $this->field = $field;
        $this->validation = $validation;
        $this->name = $name;
        $this->schemaPath = $schemaPath;
        $this->options = $options + ['sparse' => false];
    }

    /**
     * Add a validation error.
     *
     * @param string $error The message code.
     * @param array $options An array of additional information to add to the error entry or a numeric error code.
     * @return $this
     * @see Validation::addError()
     */
    public function addError(string $error, array $options = []) {
        $this->validation->addError($this->getName(), $error, $options);
        return $this;
    }

    /**
     * Add an invalid type error.
     *
     * @param mixed $value The erroneous value.
     * @param string $type The type that was checked.
     * @return $this
     */
    public function addTypeError($value, $type = '') {
        $type = $type ?: $this->getType();

        $this->validation->addError(
            $this->getName(),
            'type',
            [
                'type' => $type,
                'value' => is_scalar($value) ? $value : null,
                'messageCode' => is_scalar($value) ? "{value} is not a valid $type." : "The value is not a valid $type."
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
        $this->name = ltrim($name, '/');
        return $this;
    }

    /**
     * Get the field type.
     *
     * @return string|string[]|null Returns a type string, array of type strings, or null if there isn't one.
     */
    public function getType() {
        return $this->field['type'] ?? null;
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
        return $this->field[$key] ?? $default;
    }

    /**
     * Whether or not the field has a value.
     *
     * @param string $key The key to look at.
     * @return bool Returns **true** if the field has a key or **false** otherwise.
     */
    public function hasVal($key) {
        return isset($this->field[$key]) || (is_array($this->field) && array_key_exists($key, $this->field));
    }

    /**
     * Get the error count for this field.
     */
    public function getErrorCount() {
        return $this->getValidation()->getErrorCount($this->getName());
    }

    /**
     * Whether or not we are validating a request.
     *
     * @return bool Returns **true** of we are validating a request or **false** otherwise.
     */
    public function isRequest(): bool {
        return $this->options['request'] ?? false;
    }

    /**
     * Whether or not we are validating a response.
     *
     * @return bool Returns **true** of we are validating a response or **false** otherwise.
     */
    public function isResponse(): bool {
        return $this->options['response'] ?? false;
    }

    /**
     * Whether or not this is a sparse validation..
     *
     * @return bool Returns **true** if this is a sparse validation or **false** otherwise.
     */
    public function isSparse() {
        return $this->getOption('sparse', false);
    }

    /**
     * Gets the options array.
     *
     * @return array Returns an options array.
     */
    public function getOptions(): array {
        return $this->options;
    }

    /**
     * Get an indivitual option.
     *
     * @param string $option The name of the option.
     * @param mixed $default The default value to return if the option doesn't exist.
     * @return mixed Returns the option or the default value.
     */
    public function getOption(string $option, $default = null) {
        return $this->options[$option] ?? $default;
    }

    /**
     * Get the schemaPath.
     *
     * @return string Returns the schemaPath.
     */
    public function getSchemaPath(): string {
        return $this->schemaPath;
    }

    /**
     * Set the schemaPath.
     *
     * @param string $schemaPath
     * @return $this
     */
    public function setSchemaPath(string $schemaPath) {
        $this->schemaPath = ltrim($schemaPath, '/');
        return $this;
    }

    /**
     * Escape a JSON reference field.
     *
     * @param string $ref The reference to escape.
     * @return string Returns an escaped reference.
     */
    public static function escapeRef(string $ref): string {
        return str_replace(['~', '/'], ['~0', '~1'], $ref);
    }
}
