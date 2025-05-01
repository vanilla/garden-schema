<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema;

use Garden\Utils\ContextException;

/**
 * An exception that was built from a {@link Validation} object.
 *
 * The validation object collects errors and is mutable. Once it's ready to be thrown as an exception it gets converted
 * to an instance of the immutable {@link ValidationException} class.
 */
class ValidationException extends ContextException implements \JsonSerializable {
    /**
     * @var Validation
     */
    private $validation;

    /**
     * Initialize an instance of the {@link ValidationException} class.
     *
     * @param Validation $validation The {@link Validation} object for the exception.
     */
    public function __construct(Validation $validation) {
        $this->validation = $validation;
        parent::__construct($validation->getSummaryMessage(), $validation->getCode(), context: ["errors" => $validation->getErrorsByField()]);
    }

    /**
     * Specify data which should be serialized to JSON.
     *
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize() {
        return $this->validation->jsonSerialize();
    }

    /**
     * Get the validation object that contain specific errors.
     *
     * @return Validation Returns a validation object.
     */
    public function getValidation() {
        return $this->validation;
    }

    /**
     * Get the first validation error message from the internal Validation object.
     *
     * Useful to present a user-friendly error message when multiple validation errors may exist, but we only need the first one.
     *
     * @return string
     */
    public function getFirstValidationMessage(): string {
        $errors = $this->validation->getErrors();

        if (!empty($errors)) {
            $first = reset($errors);
            return $first['messageCode'] ?? $first['message'] ?? 'Validation error';
        }

        return 'Validation failed.';
    }
}
