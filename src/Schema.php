<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2017 Vanilla Forums Inc.
 * @license GPLv2
 */

namespace Garden\Schema;

/**
 * A class for defining and validating data schemas.
 */
class Schema implements \JsonSerializable {
    /// Constants ///

    /** Silently remove invalid parameters when validating input. */
    const VALIDATE_CONTINUE = 1;

    /** Automatically remove invalid parameters from input and trigger an E_USER_NOTICE error during validation. */
    const VALIDATE_NOTICE = 2;

    /** Throw a ValidationException when invalid parameters are encountered during validation. */
    const VALIDATE_EXCEPTION = 4;

    /// Properties ///
    protected static $types = [
        'a' => 'array',
        'o' => 'object',
        'i' => 'integer',
        'int' => 'integer',
        's' => 'string',
        'str' => 'string',
        'f' => 'float',
        'b' => 'boolean',
        'bool' => 'boolean',
        'ts' => 'timestamp',
        'dt' => 'datetime'
    ];

    /** @var string */
    protected $description = '';

    protected $schema = [];

    /** @var int */
    protected $validationBehavior = self::VALIDATE_CONTINUE;

    /**
     * @var array An array of callbacks that will custom validate the schema.
     */
    protected $validators = [];

    /// Methods ///

    /**
     * Initialize an instance of a new {@link Schema} class.
     *
     * @param array $schema The array schema to validate against.
     */
    public function __construct($schema = []) {
        $this->schema = $this->parse($schema);
    }

    /**
     * Select the first non-empty value from an array.
     *
     * @param array $keys An array of keys to try.
     * @param array $array The array to select from.
     * @param mixed $default The default value if non of the keys exist.
     * @return mixed Returns the first non-empty value of {@link $default} if none are found.
     * @category Array Functions
     */
    private static function arraySelect(array $keys, array $array, $default = null) {
        foreach ($keys as $key) {
            if (isset($array[$key]) && $array[$key]) {
                return $array[$key];
            }
        }
        return $default;
    }

    /**
     * Build an OpenAPI-compatible specification of the current schema.
     *
     * @see https://github.com/OAI/OpenAPI-Specification/blob/master/versions/2.0.md#parameter-object
     * @return array
     */
    public function dumpSpec() {
        $schema = $this->schema;

        if (is_array($schema)) {
            foreach ($schema as &$parameter) {
                if (!is_array($parameter) || !$parameter['type']) {
                    unset($parameter);
                    continue;
                }

                // Massage schema's types into their Open API v2 counterparts, including potential formatting flags.
                // Valid parameter types for OpenAPI v2: string, number, integer, boolean, array or file
                switch ($parameter['type']) {
                    case 'string':
                    case 'boolean':
                    case 'array':
                        // string, boolean and array types should not be altered.
                        break;
                    case 'object':
                        $parameter['type'] = 'array';
                        break;
                    case 'timestamp':
                        $parameter['type'] = 'integer';
                        break;
                    case 'float':
                        $parameter['type'] = 'number';
                        $parameter['format'] = 'float';
                        break;
                    case 'datetime':
                        $parameter['type'] = 'string';
                        $parameter['format'] = 'dateTime';
                        break;
                    default:
                        $parameter['type'] = 'string';
                }
            }
        } else {
            $schema = [];
        }

        $spec = [
            'description' => $this->description,
            'parameters' => $schema
        ];

        return $spec;
    }

    /**
     * Filter fields not in the schema.  The action taken is determined by the configured validation behavior.
     *
     * @param array &$data The data to filter.
     * @param array $schema The schema array.  Its configured parameters are used to filter $data.
     * @param Validation &$validation This argument will be filled with the validation result.
     * @param string $path The path to current parameters for nested objects.
     * @return Schema Returns the current instance for fluent calls.
     */
    protected function filterData(array &$data, array $schema, Validation &$validation, $path = '') {
        // Normalize schema key casing for case-insensitive data key comparisons.
        $schemaKeys = array_combine(array_map('strtolower', array_keys($schema)), array_keys($schema));

        foreach ($data as $key => $val) {
            if (array_key_exists($key, $schema)) {
                continue;
            } elseif (array_key_exists(strtolower($key), $schemaKeys)) {
                // Migrate the value to the properly-cased key.
                $correctedKey = $schemaKeys[strtolower($key)];
                $data[$correctedKey] = $data[$key];
                unset($data[$key]);
                continue;
            }

            $errorMessage = sprintft('Unexpected parameter: %1$s.', $path.$key);

            switch ($this->validationBehavior) {
                case self::VALIDATE_EXCEPTION:
                    $validation->addError(
                        $key, 'unexpected_parameter', [
                            'parameter' => $key,
                            'message' => $errorMessage,
                            'status' => 500
                        ]
                    );
                    continue;
                case self::VALIDATE_NOTICE:
                    // Trigger a notice then fall to the next case.
                    trigger_error($errorMessage, E_USER_NOTICE);
                case self::VALIDATE_CONTINUE:
                default:
                    unset($data[$key]);
            }
        }

        return $this;
    }

    /**
     * Grab the schema's current description.
     *
     * @return string
     */
    public function getDescription() {
        return $this->description;
    }

    /**
     * Set the description for the schema.
     *
     * @param string $description
     * @throws \InvalidArgumentException when the provided description is not a string.
     * @return Schema
     */
    public function setDescription($description) {
        if (is_string($description)) {
            $this->description = $description;
        } else {
            throw new \InvalidArgumentException("Invalid description type.", 500);
        }

        return $this;
    }

    /**
     * Return a Schema::VALIDATE_* constant representing the currently configured validation behavior.
     *
     * @return int Returns a Schema::VALIDATE_* constant to indicate this instance's validation behavior.
     */
    public function getValidationBehavior() {
        return $this->validationBehavior;
    }

    /**
     * Set the validation behavior for the schema, which determines how invalid properties are handled.
     *
     * @param int $validationBehavior One of the Schema::VALIDATE_* constants.
     * @return Schema Returns the current instance for fluent calls.
     */
    public function setValidationBehavior($validationBehavior) {
        switch ($validationBehavior) {
            case self::VALIDATE_CONTINUE:
            case self::VALIDATE_NOTICE:
            case self::VALIDATE_EXCEPTION:
                $this->validationBehavior = $validationBehavior;
                break;
            default:
                throw new \InvalidArgumentException('Invalid validation behavior.', 500);
        }

        return $this;
    }

    /**
     * Merge a schema with this one.
     *
     * @param Schema $schema A scheme instance. Its parameters will be merged into the current instance.
     */
    public function merge(Schema $schema) {
        $fn = function (array &$target, array $source) use (&$fn) {
            foreach ($source as $key => $val) {
                if (is_array($val) && array_key_exists($key, $target) && is_array($target[$key])) {
                    if (isset($val[0]) || isset($target[$key][0])) {
                        // This is a numeric array, so just do a merge.
                        $merged = array_merge($target[$key], $val);
                        if (is_string($merged[0])) {
                            $merged = array_keys(array_flip($merged));
                        }
                        $target[$key] = $merged;
                    } else {
                        $target[$key] = $fn($target[$key], $val);
                    }
                } else {
                    $target[$key] = $val;
                }
            }

            return $target;
        };

        $fn($this->schema, $schema->jsonSerialize());
    }

    /**
     * Parse a schema in short form into a full schema array.
     *
     * @param array $arr The array to parse into a schema.
     * @return array The full schema array.
     * @throws \InvalidArgumentException Throws an exception when an item in the schema is invalid.
     */
    public function parse(array $arr) {
        if (empty($arr)) {
            // An empty schema validates to anything.
            return [];
        } elseif (isset($arr['type'])) {
            // This is a long form schema and can be parsed as the root.
            return $this->parseNode($arr);
        } else {
            // Check for a root schema.
            $value = reset($arr);
            $key = key($arr);
            if (is_int($key)) {
                $key = $value;
                $value = null;
            }
            list ($name, $param) = $this->parseShortParam($key, $value);
            if (empty($name)) {
                return $this->parseNode($param, $value);
            }
        }

        // If we are here then this is n object schema.
        list($properties, $required) = $this->parseProperties($arr);

        $result = [
            'type' => 'object',
            'properties' => $properties,
            'required' => $required
        ];

        return array_filter($result);
    }

    /**
     * @param array $node
     * @param mixed $value
     * @return array
     */
    private function parseNode($node, $value = null) {
        if (is_array($value)) {
            // The value describes a bit more about the schema.
            switch ($node['type']) {
                case 'array':
                    if (isset($value['items'])) {
                        // The value includes array schema information.
                        $node = array_replace($node, $value);
                    } else {
                        $node['items'] = $this->parse($value);
                    }
                    break;
                case 'object':
                    // The value is a schema of the object.
                    if (isset($value['properties'])) {
                        list($node['properties']) = $this->parseProperties($value['properties']);
                    } else {
                        list($node['properties'], $required) = $this->parseProperties($value);
                        if (!empty($required)) {
                            $node['required'] = $required;
                        }
                    }
                    break;
                default:
                    $node = array_replace($node, $value);
                    break;
            }
        } elseif (is_string($value)) {
            if ($node['type'] === 'array' && $arrType = $this->getType($value)) {
                $node['items'] = ['type' => $arrType];
            } elseif (!empty($value)) {
                $node['description'] = $value;
            }
        }

        return $node;
    }

    /**
     * @param array $arr
     * @return array
     */
    private function parseProperties(array $arr) {
        $properties = [];
        $requiredProperties = [];
        foreach ($arr as $key => $value) {
            // Fix a schema specified as just a value.
            if (is_int($key)) {
                if (is_string($value)) {
                    $key = $value;
                    $value = '';
                } else {
                    throw new \InvalidArgumentException("Schema at position $key is not a valid parameter.", 500);
                }
            }

            // The parameter is defined in the key.
            list($name, $param, $required) = $this->parseShortParam($key, $value);

            $node = $this->parseNode($param, $value);

            $properties[$name] = $node;
            if ($required) {
                $requiredProperties[] = $name;
            }
        }
        return array($properties, $requiredProperties);
    }

    /**
     * Parse a short parameter string into a full array parameter.
     *
     * @param string $key The short parameter string to parse.
     * @param array $value An array of other information that might help resolve ambiguity.
     * @return array Returns an array in the form `[string name, array param, bool required]`.
     * @throws \InvalidArgumentException Throws an exception if the short param is not in the correct format.
     */
    public function parseShortParam($key, $value = []) {
        // Is the parameter optional?
        if (substr($key, -1) === '?') {
            $required = false;
            $key = substr($key, 0, -1);
        } else {
            $required = true;
        }

        // Check for a type.
        $parts = explode(':', $key);
        $name = $parts[0];
        $type = !empty($parts[1]) && isset(self::$types[$parts[1]]) ? self::$types[$parts[1]] : null;

        if ($value instanceof Schema) {
            if ($type === 'array') {
                $param = ['type' => $type, 'items' => $value];
            } else {
                $param = $value;
            }
        } elseif (isset($value['type'])) {
            $param = $value;

            if (!empty($type) && $type !== $param['type']) {
                throw new \InvalidArgumentException("Type mismatch between $type and {$param['type']} for field $name.", 500);
            }
        } else {
            if (empty($type) && !empty($parts[1])) {
                throw new \InvalidArgumentException("Invalid type {$parts[1]} for field $name.", 500);
            }
            $param = ['type' => $type];

            // Parsed required strings have a minimum length of 1.
            if ($type === 'string' && !empty($name) && $required) {
                $param['minLength'] = 1;
            }
        }

        return [$name, $param, $required];
    }

    /**
     * Add a custom validator to to validate the schema.
     *
     * @param string $fieldname The name of the field to validate, if any.
     *
     * If you are adding a validator to a deeply nested field then separate the path with dots.
     * @param callable $callback The callback to validate with.
     * @return Schema Returns `$this` for fluent calls.
     */
    public function addValidator($fieldname, callable $callback) {
        $this->validators[$fieldname][] = $callback;
        return $this;
    }

    /**
     * Require one of a given set of fields in the schema.
     *
     * @param array $required The field names to require.
     * @param string $fieldname The name of the field to attach to.
     * @param int $count The count of required items.
     * @return Schema Returns `$this` for fluent calls.
     */
    public function requireOneOf(array $required, $fieldname = '', $count = 1) {
        $result = $this->addValidator(
            $fieldname,
            function ($data, $fieldname, Validation $validation) use ($required, $count) {
                $hasCount = 0;
                $flattened = [];

                foreach ($required as $name) {
                    $flattened = array_merge($flattened, (array)$name);

                    if (is_array($name)) {
                        // This is an array of required names. They all must match.
                        $hasCountInner = 0;
                        foreach ($name as $nameInner) {
                            if (isset($data[$nameInner]) && $data[$nameInner]) {
                                $hasCountInner++;
                            } else {
                                break;
                            }
                        }
                        if ($hasCountInner >= count($name)) {
                            $hasCount++;
                        }
                    } elseif (isset($data[$name]) && $data[$name]) {
                        $hasCount++;
                    }

                    if ($hasCount >= $count) {
                        return true;
                    }
                }

                if ($count === 1) {
                    $message = 'One of {required} are required.';
                } else {
                    $message = '{count} of {required} are required.';
                }

                $validation->addError($fieldname, 'missingField', [
                    'messageCode' => $message,
                    'required' => $required,
                    'count' => $count
                ]);
                return false;
            }
        );

        return $result;
    }

    /**
     * Validate data against the schema.
     *
     * @param mixed $data The data to validate.
     * @param bool $sparse Whether or not this is a sparse validation.
     * @return mixed Returns a cleaned version of the data.
     * @throws ValidationException Throws an exception when the data does not validate against the schema.
     */
    public function validate($data, $sparse = false) {
        $validation = new Validation();

        $clean = $this->validateField($data, $this->schema, $validation, '', $sparse);

        if (!$validation->isValid()) {
            throw new ValidationException($validation);
        }

        return $clean;
    }

    /**
     * Validate data against the schema and return the result.
     *
     * @param array &$data The data to validate.
     * @param bool $sparse Whether or not to do a sparse validation.
     * @return bool Returns true if the data is valid. False otherwise.
     */
    public function isValid(array &$data, $sparse = false) {
        try {
            $this->validate($data, $sparse);
            return true;
        } catch (ValidationException $ex) {
            return false;
        }
    }

    /**
     * Validate a field.
     *
     * @param mixed $value The value to validate.
     * @param array|Schema $field Parameters on the field.
     * @param Validation $validation A validation object to add errors to.
     * @param string $name The name of the field being validated or an empty string for the root.
     * @param bool $sparse Whether or not this is a sparse validation.
     * @return mixed Returns a clean version of the value with all extra fields stripped out.
     */
    private function validateField($value, $field, Validation $validation, $name = '', $sparse = false) {
        if ($field instanceof Schema) {
            try {
                $value = $field->validate($value, $sparse);
            } catch (ValidationException $ex) {
                // The validation failed, so merge the validations together.
                $validation->merge($ex->getValidation(), $name);
            }
        } else {
            $type = isset($field['type']) ? $field['type'] : '';

            // Validate the field's type.
            $validType = true;
            switch ($type) {
                case 'boolean':
                    $validType &= $this->validateBoolean($value, $field);
                    break;
                case 'integer':
                    $validType &= $this->validateInteger($value, $field);
                    break;
                case 'float':
                    $validType &= $this->validateFloat($value, $field);
                    break;
                case 'string':
                    $validType &= $this->validateString($value, $field, $validation);
                    break;
                case 'timestamp':
                    $validType &= $this->validateTimestamp($value, $field, $validation);
                    break;
                case 'datetime':
                    $validType &= $this->validateDatetime($value, $field);
                    break;
                case 'array':
                    $validType &= $this->validateArray($value, $field, $validation, $name, $sparse);
                    break;
                case 'object':
                    $validType &= $this->validateObject($value, $field, $validation, $name, $sparse);
                    break;
                case '':
                    // No type was specified so we are valid.
                    $validType = true;
                    break;
                default:
                    throw new \InvalidArgumentException("Unrecognized type $type.", 500);
            }
            if (!$validType) {
                $this->addTypeError($validation, $name, $type);
            }
        }

        // Validate a custom field validator.
        $this->callValidators($value, $name, $validation);

        return $value;
    }

    /**
     * Add an invalid type error.
     *
     * @param Validation $validation The validation to add the error to.
     * @param string $name The full field name.
     * @param string $type The type that was checked.
     * @return $this
     */
    protected function addTypeError(Validation $validation, $name, $type) {
        $validation->addError(
            $name,
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
     * Call all of the validators attached to a field.
     *
     * @param mixed $value The field value being validated.
     * @param string $name The full path to the field.
     * @param Validation $validation The validation object to add errors.
     * @internal param array $field The field schema.
     * @internal param bool $sparse Whether this is a sparse validation.
     */
    private function callValidators($value, $name, Validation $validation) {
        // Strip array references in the name except for the last one.
        $key = preg_replace(['`\[\d+\]$`', '`\[\d+\]`'], ['[]', ''], $name);
        if (!empty($this->validators[$key])) {
            foreach ($this->validators[$key] as $validator) {
                call_user_func($validator, $value, $name, $validation);
            }
        }
    }

    /**
     * Validate an array.
     *
     * @param mixed &$value The value to validate.
     * @param array $field The field definition.
     * @param Validation $validation The validation results to add.
     * @param string $name The name of the field being validated or an empty string for the root.
     * @param bool $sparse Whether or not this is a sparse validation.
     * @return bool Returns true if {@link $value} is valid or false otherwise.
     */
    private function validateArray(&$value, array $field, Validation $validation, $name = '', $sparse = false) {
        $validType = true;

        if (!is_array($value) || (count($value) > 0 && !array_key_exists(0, $value))) {
            $validType = false;
        } else {
            if (isset($field['items'])) {
                $result = [];

                // Validate each of the types.
                foreach ($value as $i => &$item) {
                    $itemName = "{$name}[{$i}]";
                    $validItem = $this->validateField($item, $field['items'], $validation, $itemName, $sparse);
                    $result[] = $validItem;
                }
            } else {
                // Cast the items into a proper numeric array.
                $result = array_values($value);
            }
            // Set the value to the clean version of itself.
            $value = $result;
        }

        return $validType;
    }

    /**
     * Validate a boolean value.
     *
     * @param mixed &$value The value to validate.
     * @param array $field The field definition.
     * @return bool Returns true if the value is valid or false otherwise.
     * @internal param Validation $validation The validation results to add.
     */
    private function validateBoolean(&$value, array $field) {
        if (is_bool($value)) {
            $validType = true;
        } else {
            $bools = [
                '0' => false, 'false' => false, 'no' => false, 'off' => false, '' => false,
                '1' => true, 'true' => true, 'yes' => true, 'on' => true
            ];
            if ((is_string($value) || is_numeric($value)) && isset($bools[$value])) {
                $value = $bools[$value];
                $validType = true;
            } else {
                $validType = false;
            }
        }
        return $validType;
    }

    /**
     * Validate a date time.
     *
     * @param mixed &$value The value to validate.
     * @param array $field The field definition.
     * @return bool Returns true if <a href='psi_element://$value'>$value</a> is valid or false otherwise.
     * is valid or false otherwise.
     * @internal param Validation $validation The validation results to add.
     */
    private function validateDatetime(&$value, array $field) {
        $validType = true;
        if ($value instanceof \DateTime) {
            $validType = true;
        } elseif (is_string($value) && $value !== '') {
            try {
                $dt = new \DateTime($value);
                if ($dt) {
                    $value = $dt;
                } else {
                    $validType = false;
                }
            } catch (\Exception $ex) {
                $validType = false;
            }
        } elseif (is_numeric($value) && $value > 0) {
            $value = new \DateTime('@'.(string)round($value));
            $validType = true;
        } else {
            $validType = false;
        }
        return $validType;
    }

    /**
     * Validate a float.
     *
     * @param mixed &$value The value to validate.
     * @param array $field The field definition.
     * @return bool Returns true if <a href='psi_element://$value'>$value</a> is valid or false otherwise.
     * is valid or false otherwise.
     * @internal param Validation $validation The validation results to add.
     */
    private function validateFloat(&$value, array $field) {
        if (is_float($value)) {
            $validType = true;
        } elseif (is_numeric($value)) {
            $value = (float)$value;
            $validType = true;
        } else {
            $validType = false;
        }
        return $validType;
    }

    /**
     * Validate and integer.
     *
     * @param mixed &$value The value to validate.
     * @param array $field The field definition.
     * @return bool Returns true if <a href='psi_element://$value'>$value</a> is valid or false otherwise.
     * is valid or false otherwise.
     * @internal param Validation $validation The validation results to add.
     */
    private function validateInteger(&$value, array $field) {
        if (is_int($value)) {
            $validType = true;
        } elseif (is_numeric($value)) {
            $value = (int)$value;
            $validType = true;
        } else {
            $validType = false;
        }
        return $validType;
    }

    /**
     * Validate an object.
     *
     * @param mixed &$value The value to validate.
     * @param array $field The field definition.
     * @param Validation $validation The validation results to add.
     * @param string $name The name of the field being validated or an empty string for the root.
     * @param bool $sparse Whether or not this is a sparse validation.
     * @return bool Returns true if {@link $value} is valid or false otherwise.
     */
    private function validateObject(&$value, array $field, Validation $validation, $name = '', $sparse = false) {
        if (!is_array($value) || isset($value[0])) {
            return false;
        } elseif (isset($field['properties'])) {
            // Validate the data against the internal schema.
            $value = $this->validateProperties($value, $field, $validation, $name, $sparse);
        }
        return true;
    }

    /**
     * Validate data against the schema and return the result.
     *
     * @param array $data The data to validate.
     * @param array $field The schema array to validate against.
     * @param Validation $validation This argument will be filled with the validation result.
     * @param string $name The path to the current path for nested objects.
     * @param bool $sparse Whether or not this is a sparse validation.
     * @return array Returns a clean array with only the appropriate properties and the data coerced to proper types.
     */
    private function validateProperties(array $data, array $field, Validation $validation, $name = '', $sparse = false) {
        $properties = $field['properties'];
        $required = isset($field['required']) ? array_flip($field['required']) : [];
        $keys = array_keys($data);
        $keys = array_combine(array_map('strtolower', $keys), $keys);


        // Loop through the schema fields and validate each one.
        $clean = [];
        foreach ($properties as $propertyName => $propertyField) {
            $fullName = ltrim("$name.$propertyName", '.');
            $lName = strtolower($propertyName);
            $isRequired = isset($required[$propertyName]);

            // First check for required fields.
            if (!array_key_exists($lName, $keys)) {
                // A sparse validation can leave required fields out.
                if ($isRequired && !$sparse) {
                    $validation->addError($fullName, 'missingField', ['messageCode' => '{field} is required.']);
                }
            } elseif ($data[$keys[$lName]] === null) {
                $clean[$propertyName] = null;
                if ($isRequired) {
                    $validation->addError($fullName, 'missingField', ['messageCode' => '{field} cannot be null.']);
                }
            } else {
                $clean[$propertyName] = $this->validateField($data[$keys[$lName]], $propertyField, $validation, $fullName, $sparse);
            }

            unset($keys[$lName]);
        }

        // Look for extraneous properties.
        if (!empty($keys)) {
            switch ($this->getValidationBehavior()) {
                case Schema::VALIDATE_NOTICE:
                    $msg = sprintf("%s has unexpected field(s): %s.", $name ?: 'value', implode(', ', $keys));
                    trigger_error($msg, E_USER_NOTICE);
                    break;
                case Schema::VALIDATE_EXCEPTION:
                    $validation->addError($name, 'invalid', [
                        'messageCode' => '{field} has {extra,plural,an unexpected field,unexpected fields}: {extra}.',
                        'extra' => array_values($keys),
                        'status' => 422
                    ]);
                    break;
            }
        }

        return $clean;
    }

    /**
     * Validate a string.
     *
     * @param mixed &$value The value to validate.
     * @param array $field The field definition.
     * @param Validation $validation The validation results to add.
     * @param string $name The name of the field being validated.
     * @return bool Returns true if {@link $value} is valid or false otherwise.
     */
    private function validateString(&$value, array $field, Validation $validation, $name = '') {
        if (is_string($value)) {
            $validType = true;
        } elseif (is_numeric($value)) {
            $value = (string)$value;
            $validType = true;
        } else {
            return false;
        }

        if (($minLength = self::val('minLength', $field, 0)) > 0 && mb_strlen($value) < $minLength) {
            if ($minLength === 1) {
                $validation->addError($name, 'missingField', ['messageCode' => '{field} is required.', 'status' => 422]);
            } else {
                $validation->addError(
                    $name,
                    'minLength',
                    [
                        'messageCode' => '{field} should be at least {minLength} characters long.',
                        'minLength' => $minLength,
                        'status' => 422
                    ]
                );
            }
            return false;
        }
        if (($maxLength = self::val('maxLength', $field, 0)) > 0 && mb_strlen($value) > $maxLength) {
            $validation->addError(
                $name,
                'maxLength',
                [
                    'messageCode' => '{field} is {overflow} {overflow,plural,characters} too long.',
                    'maxLength' => $maxLength,
                    'overflow' => mb_strlen($value) - $maxLength,
                    'status' => 422
                ]
            );
            return false;
        }
        if ($pattern = self::val('pattern', $field)) {
            $regex = '`'.str_replace('`', preg_quote('`', '`'), $pattern).'`';

            if (!preg_match($regex, $value)) {
                $validation->addError(
                    $name,
                    'invalid',
                    [
                        'messageCode' => '{field} is in the incorrect format.',
                        'status' => 422
                    ]
                );
            }

            return false;
        }

        return $validType;
    }

    /**
     * Validate a unix timestamp.
     *
     * @param mixed &$value The value to validate.
     * @param array $field The field definition.
     * @param Validation $validation The validation results to add.
     * @return bool Returns true if {@link $value} is valid or false otherwise.
     */
    private function validateTimestamp(&$value, array $field, Validation $validation) {
        $validType = true;
        if (is_numeric($value)) {
            $value = (int)$value;
        } elseif (is_string($value) && $ts = strtotime($value)) {
            $value = $ts;
        } else {
            $validType = false;
        }
        return $validType;
    }

    /**
     * Specify data which should be serialized to JSON.
     *
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     */
    public function jsonSerialize() {
        $result = $this->schema;
        array_walk_recursive($result, function (&$value, $key) {
            if ($value instanceof \JsonSerializable) {
                $value = $value->jsonSerialize();
            }
        });
        return $result;
    }

    /**
     * Look up a type based on its alias.
     *
     * @param string $alias The type alias or type name to lookup.
     * @return mixed
     */
    private function getType($alias) {
        if (isset(self::$types[$alias])) {
            $type = self::$types[$alias];
        } elseif (array_search($alias, self::$types) !== false) {
            $type = $alias;
        } else {
            $type = null;
        }
        return $type;
    }

    /**
     * Look up a value in array.
     *
     * @param string|int $key The array key.
     * @param array $arr The array to search.
     * @param mixed $default The default if key is not found.
     * @return mixed Returns the array value or the default.
     */
    private static function val($key, array $arr, $default = null) {
        return isset($arr[$key]) ? $arr[$key] : $default;
    }
}

function sprintft($format, ...$args) {
    return sprintf($format, ...$args);
}
