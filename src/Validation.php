<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema;

/**
 * An class for collecting validation errors.
 */
class Validation {
    /**
     * @var array
     */
    private $errors = [];

    /**
     * @var string
     */
    private $mainMessage = '';

    /**
     * @var int
     */
    private $mainNumber = 0;

    /**
     * @var bool Whether or not fields should be translated.
     */
    private $translateFieldNames = false;

    /**
     * Add an error.
     *
     * @param string $field The name and path of the field to add or an empty string if this is a global error.
     * @param string $error The message code.
     * @param array $options An array of additional information to add to the error entry or a numeric error code.
     *
     * - **messageCode**: A specific message translation code for the final error.
     * - **number**: An error number for the error.
     * - Error specific fields can be added to format a custom error message.
     * @return $this
     */
    public function addError(string $field, string $error, $options = []) {
        if (empty($error)) {
            throw new \InvalidArgumentException('The error code cannot be empty.', 500);
        } elseif (!in_array(gettype($options), ['integer', 'array'], true)) {
            throw new \InvalidArgumentException('$options must be an integer or array.', 500);
        }
        if (is_int($options)) {
            trigger_error('Passing an integer for $options in Validation::addError() is deprecated.', E_USER_DEPRECATED);
            $options = ['number' => $options];
        } elseif (isset($options['status'])) {
            trigger_error('Validation::addError() expects $options[\'number\'], not $options[\'status\'].', E_USER_DEPRECATED);
            $options['number'] = $options['status'];
            unset($options['status']);
        }

        $row = ['error' => $error] + $options;
        $this->errors[$field][] = $row;

        return $this;
    }

    /**
     * Get the error number.
     *
     * The number is an HTTP response code and should be of the 4xx variety.
     *
     * @return int Returns an error number.
     */
    public function getNumber(): int {
        if ($status = $this->getMainNumber()) {
            return $status;
        }

        if ($this->isValid()) {
            return 200;
        }

        // There was no status so loop through the errors and look for the highest one.
        $maxNumber = 0;
        foreach ($this->getRawErrors() as $error) {
            if (isset($error['number']) && $error['number'] > $maxNumber) {
                $maxNumber = $error['number'];
            }
        }

        return $maxNumber?: 400;
    }

    /**
     * Get or set the error status code.
     *
     * The status code is an http response code and should be of the 4xx variety.
     *
     * @return int Returns the current status code.
     * @deprecated
     */
    public function getStatus(): int {
        trigger_error("Validation::getStatus() is deprecated. Use Validation::getNumber() instead.", E_USER_DEPRECATED);
        return $this->getNumber();
    }

    /**
     * Get the message for this exception.
     *
     * @return string Returns the exception message.
     */
    public function getMessage(): string {
        if ($message = $this->getMainMessage()) {
            return $message;
        }

        return $this->getConcatMessage();
    }

    /**
     * Gets all of the errors as a flat array.
     *
     * The errors are internally stored indexed by field. This method flattens them for final error returns.
     *
     * @return array Returns all of the errors.
     */
    public function getErrors(): array {
        $result = [];
        foreach ($this->getRawErrors() as $field => $error) {
            $result[] = $this->pluckError(['field' => $field] + $error);
        }
        return $result;
    }

    /**
     * Get the errors for a specific field.
     *
     * @param string $field The full path to the field.
     * @return array Returns an array of errors.
     */
    public function getFieldErrors(string $field): array {
        if (empty($this->errors[$field])) {
            return [];
        } else {
            $result = [];
            foreach ($this->errors[$field] as $error) {
                $result[] = $this->pluckError($error + ['field' => $field]);
            }
            return $result;
        }
    }

    /**
     * Gets all of the errors as a flat array.
     *
     * The errors are internally stored indexed by field. This method flattens them for final error returns.
     *
     * @return \Traversable Returns all of the errors.
     */
    protected function getRawErrors() {
        foreach ($this->errors as $field => $errors) {
            foreach ($errors as $error) {
                yield $field => $error;
            }
        }
    }

    /**
     * Check whether or not the validation is free of errors.
     *
     * @return bool Returns true if there are no errors, false otherwise.
     */
    public function isValid(): bool {
        return empty($this->errors);
    }

    /**
     * Check whether or not a particular field is has errors.
     *
     * @param string $field The name of the field to check for validity.
     * @return bool Returns true if the field has no errors, false otherwise.
     */
    public function isValidField(string $field): bool {
        $result = empty($this->errors[$field]);
        return $result;
    }

    /**
     * Get the error count, optionally for a particular field.
     *
     * @param string $field The name of a field or an empty string for all errors.
     * @return int Returns the error count.
     */
    public function getErrorCount($field = '') {
        if (empty($field)) {
            return iterator_count($this->getRawErrors());
        } elseif (empty($this->errors[$field])) {
            return 0;
        } else {
            return count($this->errors[$field]);
        }
    }

    /**
     * Get the error message for an error row.
     *
     * @param array $error The error row.
     * @return string Returns a formatted/translated error message.
     */
    private function formatErrorMessage(array $error) {
        if (isset($error['messageCode'])) {
            $messageCode = $error['messageCode'];
        } elseif (isset($error['message'])) {
            return $error['message'];
        } else {
            $messageCode = $error['error'];
        }

        // Massage the field name for better formatting.
        $msg = $this->formatMessage($messageCode, $error);
        return $msg;
    }

    /**
     * Format a value for output in a message.
     *
     * @param mixed $value The value to format.
     * @return string Returns the formatted value.
     */
    protected function formatValue($value): string {
        if (is_string($value) && mb_strlen($value) > 20) {
            $value = mb_substr($value, 0, 20).'…';
        }

        if (is_scalar($value)) {
            return json_encode($value);
        } else {
            return $this->translate('value');
        }
    }

    /**
     * Expand and translate a message format against an array of values.
     *
     * @param string $format The message format.
     * @param array $context The context arguments to apply to the message.
     * @return string Returns a formatted string.
     */
    private function formatMessage($format, $context = []) {
        $format = $this->translate($format);

        $msg = preg_replace_callback('`({[^{}]+})`', function ($m) use ($context) {
            $args = array_filter(array_map('trim', explode(',', trim($m[1], '{}'))));
            $field = array_shift($args);

            switch ($field) {
                case 'value':
                    return $this->formatValue($context[$field] ?? null);
                case 'field':
                    $field = $context['field'] ?: 'value';
                    if ($this->getTranslateFieldNames()) {
                        return $this->translate($field);
                    } else {
                        return $field;
                    }
                default:
                    return $this->formatField(isset($context[$field]) ? $context[$field] : null, $args);
            }
        }, $format);
        return $msg;
    }

    /**
     * Translate an argument being placed in an error message.
     *
     * @param mixed $value The argument to translate.
     * @param array $args Formatting arguments.
     * @return string Returns the translated string.
     */
    private function formatField($value, array $args = []) {
        if ($value === null) {
            $r = $this->translate('null');
        } elseif ($value === true) {
            $r = $this->translate('true');
        } elseif ($value === false) {
            $r = $this->translate('false');
        } elseif (is_string($value)) {
            $r = $this->translate($value);
        } elseif (is_numeric($value)) {
            $r = $value;
        } elseif (is_array($value)) {
            $argArray = array_map([$this, 'formatField'], $value);
            $r = implode(', ', $argArray);
        } elseif ($value instanceof \DateTimeInterface) {
            $r = $value->format('c');
        } else {
            $r = $value;
        }

        $format = array_shift($args);
        switch ($format) {
            case 'plural':
                $singular = array_shift($args);
                $plural = array_shift($args) ?: $singular.'s';
                $count = is_array($value) ? count($value) : $value;
                $r = $count == 1 ? $singular : $plural;
                break;
        }

        return (string)$r;
    }

    /**
     * Translate a string.
     *
     * This method doesn't do any translation itself, but is meant for subclasses wanting to add translation ability to
     * this class.
     *
     * @param string $str The string to translate.
     * @return string Returns the translated string.
     */
    public function translate(string $str): string {
        if (substr($str, 0, 1) === '@') {
            // This is a literal string that bypasses translation.
            return substr($str, 1);
        } else {
            return $str;
        }
    }

    /**
     * Merge another validation object with this one.
     *
     * @param Validation $validation The validation object to merge.
     * @param string $name The path to merge to. Use this parameter when the validation object is meant to be a subset of this one.
     * @return $this
     */
    public function merge(Validation $validation, $name = '') {
        $paths = $validation->errors;

        foreach ($paths as $path => $errors) {
            foreach ($errors as $error) {
                if (strlen($name) > 0) {
                    // We are merging a sub-schema error that did not occur on a particular property of the sub-schema.
                    if ($path === '') {
                        $fullPath = $name;
                    } else {
                        $fullPath = "{$name}/{$path}";
                    }
                    $this->addError($fullPath, $error['error'], $error);
                }
            }
        }
        return $this;
    }

    /**
     * Get the main error message.
     *
     * If set, this message will be returned as the error message. Otherwise the message will be set from individual
     * errors.
     *
     * @return string Returns the main message.
     */
    public function getMainMessage() {
        return $this->mainMessage;
    }

    /**
     * Set the main error message.
     *
     * @param string $message The new message.
     * @return $this
     */
    public function setMainMessage($message) {
        $this->mainMessage = $message;
        return $this;
    }

    /**
     * Get the main error number.
     *
     * @return int Returns an HTTP response code or zero to indicate it should be calculated.
     */
    public function getMainNumber(): int {
        return $this->mainNumber;
    }

    /**
     * Set the main error number.
     *
     * @param int $status An HTTP response code or zero.
     * @return $this
     */
    public function setMainNumber(int $status) {
        $this->mainNumber = $status;
        return $this;
    }

    /**
     * Get the main error number.
     *
     * @return int Returns an HTTP response code or zero to indicate it should be calculated.
     * @deprecated
     */
    public function getMainStatus(): int {
        trigger_error("Validation::getMainStatus() is deprecated. Use Validation::getMainNumber() instead.", E_USER_DEPRECATED);
        return $this->mainNumber;
    }

    /**
     * Set the main error number.
     *
     * @param int $status An HTTP response code or zero.
     * @return $this
     * @deprecated
     */
    public function setMainStatus(int $status) {
        trigger_error("Validation::setMainStatus() is deprecated. Use Validation::getMainNumber() instead.", E_USER_DEPRECATED);
        $this->mainNumber = $status;
        return $this;
    }


    /**
     * Whether or not fields should be translated.
     *
     * @return bool Returns **true** if field names are translated or **false** otherwise.
     */
    public function getTranslateFieldNames() {
        return $this->translateFieldNames;
    }

    /**
     * Set whether or not fields should be translated.
     *
     * @param bool $translate Whether or not fields should be translated.
     * @return $this
     */
    public function setTranslateFieldNames($translate) {
        $this->translateFieldNames = $translate;
        return $this;
    }

    /**
     * Format a raw error row for consumption.
     *
     * @param array $error The error to format.
     * @return array Returns the error stripped of default values.
     */
    private function pluckError(array $error) {
        $row = array_intersect_key(
            $error,
            ['field' => 1, 'error' => 1, 'number' => 1]
        ) + ['number' => 400];

        $row['message'] = $this->formatErrorMessage($error);
        return $row;
    }

    /**
     * Generate a global error string by concatenating field errors.
     *
     * @param string|null $field The name of a field to concatenate errors for.
     * @return string Returns an error message.
     */
    public function getConcatMessage($field = null): string {
        $sentence = $this->translate('%s.');

        $errors = $field === null ? $this->getRawErrors() : ($this->errors[$field] ?? []);

        // Generate the message by concatenating all of the errors together.
        $messages = [];
        foreach ($errors as $field => $error) {
            $message = $this->formatErrorMessage($error + ['field' => $field]);
            if (preg_match('`\PP$`u', $message)) {
                $message = sprintf($sentence, $message);
            }
            $messages[] = $message;
        }
        return implode(' ', $messages);
    }
}
