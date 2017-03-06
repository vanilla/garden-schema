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
    const VALIDATE_REMOVE = 1;

    /** Automatically remove invalid parameters from input and trigger an E_USER_NOTICE error during validation. */
    const VALIDATE_NOTICE = 2;

    /** Throw a ValidationException when invalid parameters are encountered during validation. */
    const VALIDATE_EXCEPTION = 4;

    /// Properties ///
    protected static $types = [
//        '@' => 'file',
        'a' => 'array',
        'o' => 'object',
        '=' => 'base64',
        'i' => 'integer',
        's' => 'string',
        'f' => 'float',
        'b' => 'boolean',
        'ts' => 'timestamp',
        'dt' => 'datetime'
    ];
    /** @var string */
    protected $description = '';
    protected $schema = [];
    /** @var int */
    protected $validationBehavior = self::VALIDATE_NOTICE;

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
        $this->schema = static::parseSchema($schema);
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
     * Create a new schema and return it.
     *
     * @param array $schema The schema array.
     * @return Schema Returns the newly created and parsed schema.
     */
    public static function create($schema = []) {
        $new = new Schema($schema);
        return $new;
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
                    case 'base64':
                        $parameter['type'] = 'string';
                        $parameter['format'] = 'byte';
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
                        'unexpected_parameter',
                        $key,
                        [
                            'parameter' => $key,
                            'message' => $errorMessage,
                            'status' => 500
                        ]
                    );
                    continue;
                case self::VALIDATE_NOTICE:
                    // Trigger a notice then fall to the next case.
                    trigger_error($errorMessage, E_USER_NOTICE);
                case self::VALIDATE_REMOVE:
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
            case self::VALIDATE_REMOVE:
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
     * Get the schema's currently configured parameters.
     *
     * @return array
     */
    public function getParameters() {
        return $this->schema;
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
                    $target[$key] = $fn($target[$key], $val);
                } else {
                    $target[$key] = $val;
                }
            }

            return $target;
        };

        $fn($this->schema, $schema->getParameters());
    }

    /**
     * Parse a schema in short form into a full schema array.
     *
     * @param array $arr The array to parse into a schema.
     * @return array The full schema array.
     * @throws \InvalidArgumentException Throws an exception when an item in the schema is invalid.
     */
    public static function parseSchema(array $arr) {
        // Check for a long form schema.
        if (isset($arr['type'])) {
            $arr = [':?' => $arr];
        }

        $properties = [];
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
            list($name, $param) = static::parseShortParam($key, $value);

            if (is_array($value)) {
                // The value describes a bit more about the schema.
                switch ($param['type']) {
                    case 'array':
                        if (isset($value['items'])) {
                            // The value includes array schema information.
                            $param = array_replace($param, $value);
                        } elseif (isset($value['type'])) {
                            // The value is a long-form schema.
                            $param['items'] = $value;
                        } else {
                            // The value is another shorthand schema.
                            $param['items'] = [
                                'type' => 'object',
                                'required' => true,
                                'properties' => static::parseSchema($value)
                            ];
                        }
                        break;
                    case 'object':
                        // The value is a schema of the object.
                        if (isset($value['properties'])) {
                            $param['properties'] = static::parseSchema($value['properties'])['properties'];
                        } else {
                            $param['properties'] = static::parseSchema($value)['properties'];
                        }
                        break;
                    default:
                        $param = array_replace($param, $value);
                        break;
                }
            } elseif (is_string($value)) {
                if ($param['type'] === 'array') {
                    // Check to see if the value is the item type in the array.
                    if (isset(self::$types[$value])) {
                        $arrType = self::$types[$value];
                    } elseif (($index = array_search($value, self::$types)) !== false) {
                        $arrType = self::$types[$index];
                    }

                    if (isset($arrType)) {
                        $param['items'] = ['type' => $arrType, 'required' => true];
                        $value = '';
                    }
                }

                if (!empty($value)) {
                    // The value is the schema description.
                    $param['description'] = $value;
                }
            }

            $properties[$name] = $param;
        }

        if (array_key_exists('', $properties)) {
            $result = $properties[''];
        } else {
            $result = [
                'type' => 'object',
                'properties' => $properties
            ];
        }

        return $result;
    }

    /**
     * Parse a short parameter string into a full array parameter.
     *
     * @param string $str The short parameter string to parse.
     * @param array $other An array of other information that might help resolve ambiguity.
     * @return array Returns an array in the form [name, [param]].
     * @throws \InvalidArgumentException Throws an exception if the short param is not in the correct format.
     */
    public static function parseShortParam($str, $other = []) {
        // Is the parameter optional?
        if (self::strEnds($str, '?')) {
            $required = false;
            $str = substr($str, 0, -1);
        } else {
            $required = true;
        }

        // Check for a type.
        $parts = explode(':', $str);
        $name = $parts[0];
        $type = !empty($parts[1]) && isset(self::$types[$parts[1]]) ? self::$types[$parts[1]] : null;


        if ($other instanceof Schema) {
            $param = $other;
        } elseif (isset($other['type'])) {
            $param = $other;

            if (!empty($type) && $type !== $param['type']) {
                throw new \InvalidArgumentException("Type mismatch between $type and {$param['type']} for field $name.", 500);
            }
        } else {
            if (empty($type) && !empty($parts[1])) {
                throw new \InvalidArgumentException("Invalid type {$parts[1]} for field $name.", 500);
            }
            $param = ['type' => $type];
            if ($required) {
                $param['required'] = $required;
            }
        }

        return [$name, $param];
    }

    /**
     * Add a custom validator to to validate the schema.
     *
     * @param string $fieldname The name of the field to validate, if any.
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
     * @param array $fieldnames The field names to require.
     * @param int $count The count of required items.
     * @return Schema Returns `$this` for fluent calls.
     */
    public function requireOneOf(array $fieldnames, $count = 1) {
        $result = $this->addValidator('*', function ($data, Validation $validation) use ($fieldnames, $count) {
            $hasCount = 0;
            $flattened = [];

            foreach ($fieldnames as $name) {
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

            $messageFields = array_map(function ($v) {
                if (is_array($v)) {
                    return '('.implode(', ', $v).')';
                }
                return $v;
            }, $fieldnames);

            if ($count === 1) {
                $message = sprintft('One of %s are required.', implode(', ', $messageFields));
            } else {
                $message = sprintft('%1$s of %2$s are required.', $count, implode(', ', $messageFields));
            }

            $validation->addError('missing_field', $flattened, [
                'message' => $message
            ]);
            return false;
        });

        return $result;
    }

    /**
     * Validate data against the schema.
     *
     * @param array &$data The data to validate.
     * @param Validation &$validation This argument will be filled with the validation result.
     * @return bool Returns true if the data is valid, false otherwise.
     * @throws ValidationException Throws an exception when the data does not validate against the schema.
     */
    public function validate(array &$data, Validation &$validation = null) {
        if ($validation === null) {
            $validation = new Validation();
        }

        $this->validateField($data, $this->schema, $validation);

        if (!$validation->isValid()) {
            throw new ValidationException($validation);
        }

        return $this;
    }

    /**
     * Validate data against the schema and return the result.
     *
     * @param array &$data The data to validate.
     * @param Validation &$validation This argument will be filled with the validation result.
     * @return bool Returns true if the data is valid. False otherwise.
     */
    public function isValid(array &$data, Validation &$validation = null) {
        try {
            $this->validate($data, $validation);
            return $validation->isValid();
        } catch (ValidationException $ex) {
            return false;
        }
    }

    /**
     * Validate data against the schema and return the result.
     *
     * @param array &$data The data to validate.
     * @param array $properties The schema array to validate against.
     * @param Validation &$validation This argument will be filled with the validation result.
     * @param string $path The path to the current path for nested objects.
     * @return bool Returns true if the data is valid. False otherwise.
     */
    protected function validateProperties(array &$data, array $properties, Validation &$validation, $path = '') {
        $this->filterData($data, $properties, $validation, $path);

        // Loop through the schema fields and validate each one.
        foreach ($properties as $name => $field) {
            // Prepend the path the field label.
            if ($path) {
                $field['path'] = $path.self::arraySelect(['path', 'name'], $field);
            }

            if (array_key_exists($name, $data)) {
                $this->validateField($data[$name], $field, $validation);
            } elseif (!empty($field['required'])) {
                $validation->addError('missing_field', self::arraySelect(['path', 'name'], $field));
            }
        }

        // Validate the global validators.
        if ($path == '' && isset($this->validators['*'])) {
            foreach ($this->validators['*'] as $callback) {
                call_user_func($callback, $data, $validation);
            }
        }

        return $validation->isValid();
    }

    /**
     * Returns whether or not a string ends with another string.
     *
     * This function is not case-sensitive.
     *
     * @param string $haystack The string to test.
     * @param string $needle The substring to test against.
     * @return bool Whether or not `$string` ends with `$with`.
     * @category String Functions
     */
    private static function strEnds($haystack, $needle) {
        return strcasecmp(substr($haystack, -strlen($needle)), $needle) === 0;
    }

    /**
     * Validate a field.
     *
     * @param mixed &$value The value to validate.
     * @param array $field Parameters on the field.
     * @param Validation $validation A validation object to add errors to.
     * @throws \InvalidArgumentException Throws an exception when there is something wrong in the {@link $params}.
     * @internal param string $fieldname The name of the field to validate.
     * @return bool Returns true if the field is valid, false otherwise.
     */
    protected function validateField(&$value, array $field, Validation $validation) {
        $path = self::arraySelect(['path', 'name'], $field);
        $type = isset($field['type']) ? $field['type'] : '';
        $valid = true;

        // Check required first.
        // A value that isn't passed should fail the required test, but short circuit the other ones.
        $validRequired = $this->validateRequired($value, $field, $validation);
        if ($validRequired !== null) {
            return $validRequired;
        }

        // Validate the field's type.
        $validType = true;
        switch ($type) {
            case 'boolean':
                $validType &= $this->validateBoolean($value, $field, $validation);
                break;
            case 'integer':
                $validType &= $this->validateInteger($value, $field, $validation);
                break;
            case 'float':
                $validType &= $this->validateFloat($value, $field, $validation);
                break;
            case 'string':
                $validType &= $this->validateString($value, $field, $validation);
                break;
            case 'timestamp':
                $validType &= $this->validateTimestamp($value, $field, $validation);
                break;
            case 'datetime':
                $validType &= $this->validateDatetime($value, $field, $validation);
                break;
            case 'base64':
                $validType &= $this->validateBase64($value, $field, $validation);
                break;
            case 'array':
                $validType &= $this->validateArray($value, $field, $validation);
                break;
            case 'object':
                $validType &= $this->validateObject($value, $field, $validation);
                break;
            case '':
                // No type was specified so we are valid.
                $validType = true;
                break;
            default:
                throw new \InvalidArgumentException("Unrecognized type $type.", 500);
        }
        if (!$validType) {
            $valid = false;
            $validation->addError(
                'invalid_type',
                $path,
                [
                    'type' => $type,
                    'message' => sprintft('%1$s is not a valid %2$s.', $path, $type),
                    'status' => 422
                ]
            );
        }

        // Validate a custom field validator.
        $validatorName = isset($field['validatorName']) ? $field['validatorName'] : $path;
        if (isset($this->validators[$validatorName])) {
            foreach ($this->validators[$validatorName] as $callback) {
                call_user_func_array($callback, [&$value, $field, $validation]);
            }
        }

        return $valid;
    }

    /**
     * Validate an array.
     *
     * @param mixed &$value The value to validate.
     * @param array $field The field definition.
     * @param Validation $validation The validation results to add.
     * @return bool Returns true if {@link $value} is valid or false otherwise.
     */
    protected function validateArray(&$value, array $field, Validation $validation) {
        $validType = true;

        if (!is_array($value) || (count($value) > 0 && !array_key_exists(0, $value))) {
            $validType = false;
        } else {
            // Cast the items into a proper numeric array.
            $value = array_values($value);

            if (isset($field['items'])) {
                // Validate each of the types.
                $path = self::arraySelect(['path', 'name'], $field);
                $itemField = $field['items'];
                $itemField['validatorName'] = self::arraySelect(['validatorName', 'path', 'name'], $field).'.items';
                foreach ($value as $i => &$item) {
                    $itemField['path'] = "$path.$i";
                    $this->validateField($item, $itemField, $validation);
                }
            }
        }
        return $validType;
    }

    /**
     * Validate a base64 string.
     *
     * @param mixed &$value The value to validate.
     * @param array $field The field definition.
     * @param Validation $validation The validation results to add.
     * @return bool Returns true if {@link $value} is valid or false otherwise.
     */
    protected function validateBase64(&$value, array $field, Validation $validation) {
        if (!is_string($value)) {
            $validType = false;
        } else {
            if (!preg_match('`^[a-zA-Z0-9/+]*={0,2}$`', $value)) {
                $validType = false;
            } else {
                $decoded = @base64_decode($value);
                if ($decoded === false) {
                    $validType = false;
                } else {
                    $value = $decoded;
                    $validType = true;
                }
            }
        }
        return $validType;
    }

    /**
     * Validate a boolean value.
     *
     * @param mixed &$value The value to validate.
     * @param array $field The field definition.
     * @param Validation $validation The validation results to add.
     * @return bool Returns true if {@link $value} is valid or false otherwise.
     */
    protected function validateBoolean(&$value, array $field, Validation $validation) {
        if (is_bool($value)) {
            $validType = true;
        } else {
            $bools = [
                '0' => false, 'false' => false, 'no' => false, 'off' => false,
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
     * @param Validation $validation The validation results to add.
     * @return bool Returns true if {@link $value} is valid or false otherwise.
     */
    protected function validateDatetime(&$value, array $field, Validation $validation) {
        $validType = true;
        if ($value instanceof \DateTime) {
            $validType = true;
        } elseif (is_string($value)) {
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
     * @param Validation $validation The validation results to add.
     * @return bool Returns true if {@link $value} is valid or false otherwise.
     */
    protected function validateFloat(&$value, array $field, Validation $validation) {
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
     * @param Validation $validation The validation results to add.
     * @return bool Returns true if {@link $value} is valid or false otherwise.
     */
    protected function validateInteger(&$value, array $field, Validation $validation) {
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
     * @return bool Returns true if {@link $value} is valid or false otherwise.
     */
    protected function validateObject(&$value, array $field, Validation $validation) {
        if (!is_array($value) || isset($value[0])) {
            return false;
        } elseif (isset($field['properties'])) {
            $path = self::arraySelect(['path', 'name'], $field);
            // Validate the data against the internal schema.
            $this->validateProperties($value, $field['properties'], $validation, $path.'.');
        }
        return true;
    }

    /**
     * Validate a required field.
     *
     * @param mixed &$value The field value.
     * @param array $field The field definition.
     * @param Validation $validation A {@link Validation} object to collect errors.
     * @return bool|null Returns one of the following:
     * - null: The field is not required.
     * - true: The field is required and {@link $value} is not empty.
     * - false: The field is required and {@link $value} is empty.
     */
    protected function validateRequired(&$value, array $field, Validation $validation) {
        $required = !empty($field['required']);
        $type = $field['type'];

        if ($value === '' || $value === null) {
            if (!$required) {
                $value = null;
                return true;
            }

            switch ($type) {
                case 'boolean':
                    $value = false;
                    return true;
                case 'string':
                    $minLength = (isset($field['minLength']) ? $field['minLength'] : 1);
                    if ($minLength == 0) {
                        $value = '';
                        return true;
                    }
            }
            $validation->addError('missing_field', $field);
            return false;
        }
        return null;
    }

    /**
     * Validate a string.
     *
     * @param mixed &$value The value to validate.
     * @param array $field The field definition.
     * @param Validation $validation The validation results to add.
     * @return bool Returns true if {@link $value} is valid or false otherwise.
     */
    protected function validateString(&$value, array $field, Validation $validation) {
        if (is_string($value)) {
            $validType = true;
        } elseif (is_numeric($value)) {
            $value = (string)$value;
            $validType = true;
        } else {
            $validType = false;
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
    protected function validateTimestamp(&$value, array $field, Validation $validation) {
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
        return $this->schema;
    }
}

function sprintft($format, ...$args) {
    return sprintf($format, ...$args);
}

function val($key, array $arr, $default = null) {
    return isset($arr[$key]) ? $arr[$key] : $default;
}
