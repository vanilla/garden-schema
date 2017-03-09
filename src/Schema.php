<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2017 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema;

/**
 * A class for defining and validating data schemas.
 */
class Schema implements \JsonSerializable {
    /// Constants ///

    /**
     * Throw a notice when extraneous properties are encountered during validation.
     */
    const FLAG_EXTRA_PROPERTIES_NOTICE = 0x1;

    /**
     * Throw a ValidationException when extraneous properties are encountered during validation.
     */
    const FLAG_EXTRA_PROPERTIES_EXCEPTION = 0x2;

    /**
     * @var array All the known types.
     *
     * If this is ever given some sort of public access then remove the static.
     */
    private static $types = [
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

    private $schema = [];

    /**
     * @var int A bitwise combination of the various **Schema::FLAG_*** constants.
     */
    private $flags = 0;

    /**
     * @var array An array of callbacks that will custom validate the schema.
     */
    private $validators = [];

    /**
     * @var string|Validation The name of the class or an instance that will be cloned.
     */
    private $validationClass = Validation::class;


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
     * Grab the schema's current description.
     *
     * @return string
     */
    public function getDescription() {
        return isset($this->schema['description']) ? $this->schema['description'] : '';
    }

    /**
     * Set the description for the schema.
     *
     * @param string $description The new description.
     * @throws \InvalidArgumentException Throws an exception when the provided description is not a string.
     * @return Schema
     */
    public function setDescription($description) {
        if (is_string($description)) {
            $this->schema['description'] = $description;
        } else {
            throw new \InvalidArgumentException("The description is not a valid string.", 500);
        }

        return $this;
    }

    /**
     * Return the validation flags.
     *
     * @return int Returns a bitwise combination of flags.
     */
    public function getFlags() {
        return $this->flags;
    }

    /**
     * Set the validation flags.
     *
     * @param int $flags One or more of the **Schema::FLAG_*** constants.
     * @return Schema Returns the current instance for fluent calls.
     */
    public function setFlags($flags) {
        if (!is_int($flags)) {
            throw new \InvalidArgumentException('Invalid flags.', 500);
        }
        $this->flags = $flags;

        return $this;
    }

    /**
     * Whether or not the schema has a flag (or combination of flags).
     *
     * @param int $flag One or more of the **Schema::VALIDATE_*** constants.
     * @return bool Returns **true** if all of the flags are set or **false** otherwise.
     */
    public function hasFlag($flag) {
        return ($this->flags & $flag) === $flag;
    }

    /**
     * Set a flag.
     *
     * @param int $flag One or more of the **Schema::VALIDATE_*** constants.
     * @param bool $value Either true or false.
     * @return $this
     */
    public function setFlag($flag, $value) {
        if ($value) {
            $this->flags = $this->flags | $flag;
        } else {
            $this->flags = $this->flags & ~$flag;
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
     * Parse a schema node.
     *
     * @param array $node The node to parse.
     * @param mixed $value Additional information from the node.
     * @return array Returns a JSON schema compatible node.
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
     * Parse the schema for an object's properties.
     *
     * @param array $arr An object property schema.
     * @return array Returns a schema array suitable to be placed in the **properties** key of a schema.
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
        $validation = $this->createValidation();

        $clean = $this->validateField($data, $this->schema, $validation, '', $sparse);

        if (!$validation->isValid()) {
            throw new ValidationException($validation);
        }

        return $clean;
    }

    /**
     * Validate data against the schema and return the result.
     *
     * @param array $data The data to validate.
     * @param bool $sparse Whether or not to do a sparse validation.
     * @return bool Returns true if the data is valid. False otherwise.
     */
    public function isValid($data, $sparse = false) {
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
                    $validType &= $this->validateBoolean($value, $field, $validation, $name);
                    break;
                case 'integer':
                    $validType &= $this->validateInteger($value, $field, $validation, $name);
                    break;
                case 'float':
                    $validType &= $this->validateFloat($value, $field, $validation, $name);
                    break;
                case 'string':
                    $validType &= $this->validateString($value, $field, $validation, $name);
                    break;
                case 'timestamp':
                    $validType &= $this->validateTimestamp($value, $field, $validation, $name);
                    break;
                case 'datetime':
                    $validType &= $this->validateDatetime($value, $field, $validation, $name);
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
            } elseif (!empty($field['enum'])) {
                $this->validateEnum($value, $field, $validation, $name);
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
     * @param Validation $validation The validation results to add.
     * @param string $name The name of the field being validated or an empty string for the root.
     * @return bool Returns true if the value is valid or false otherwise.
     * @internal param Validation $validation The validation results to add.
     */
    private function validateBoolean(&$value, array $field, Validation $validation, $name) {
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
     * @param Validation $validation The validation results to add.
     * @param string $name The name of the field being validated or an empty string for the root.
     * @return bool Returns true if <a href='psi_element://$value'>$value</a> is valid or false otherwise.
     * is valid or false otherwise.
     * @internal param Validation $validation The validation results to add.
     */
    private function validateDatetime(&$value, array $field, Validation $validation, $name) {
        $validType = true;
        if ($value instanceof \DateTimeInterface) {
            $validType = true;
        } elseif (is_string($value) && $value !== '') {
            try {
                $dt = new \DateTimeImmutable($value);
                if ($dt) {
                    $value = $dt;
                } else {
                    $validType = false;
                }
            } catch (\Exception $ex) {
                $validType = false;
            }
        } elseif (is_numeric($value) && $value > 0) {
            $value = new \DateTimeImmutable('@'.(string)round($value));
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
     * @param string $name The name of the field being validated or an empty string for the root.
     * @return bool Returns true if <a href='psi_element://$value'>$value</a> is valid or false otherwise.
     * is valid or false otherwise.
     * @internal param Validation $validation The validation results to add.
     */
    private function validateFloat(&$value, array $field, Validation $validation, $name) {
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
     * @param string $name The name of the field being validated or an empty string for the root.
     * @return bool Returns true if <a href='psi_element://$value'>$value</a> is valid or false otherwise.
     * is valid or false otherwise.
     * @internal param Validation $validation The validation results to add.
     */
    private function validateInteger(&$value, array $field, Validation $validation, $name) {
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
            if ($this->hasFlag(Schema::FLAG_EXTRA_PROPERTIES_NOTICE)) {
                $msg = sprintf("%s has unexpected field(s): %s.", $name ?: 'value', implode(', ', $keys));
                trigger_error($msg, E_USER_NOTICE);
            }

            if ($this->hasFlag(Schema::FLAG_EXTRA_PROPERTIES_EXCEPTION)) {
                $validation->addError($name, 'invalid', [
                    'messageCode' => '{field} has {extra,plural,an unexpected field,unexpected fields}: {extra}.',
                    'extra' => array_values($keys),
                    'status' => 422
                ]);
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
    private function validateString(&$value, array $field, Validation $validation, $name) {
        if (is_string($value)) {
            $validType = true;
        } elseif (is_numeric($value)) {
            $value = (string)$value;
            $validType = true;
        } else {
            return false;
        }

        if (($minLength = self::val('minLength', $field, 0)) > 0 && mb_strlen($value) < $minLength) {
            if (!empty($name) && $minLength === 1) {
                $validation->addError($name, 'missingField', ['messageCode' => '{field} is required.', 'status' => 422]);
            } else {
                $validation->addError(
                    $name,
                    'minLength',
                    [
                        'messageCode' => '{field} should be at least {minLength} {minLength,plural,character} long.',
                        'minLength' => $minLength,
                        'status' => 422
                    ]
                );
            }
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
        }

        return $validType;
    }

    /**
     * Validate a unix timestamp.
     *
     * @param mixed &$value The value to validate.
     * @param array $field The field definition.
     * @param Validation $validation The validation results to add.
     * @param string $name The name of the field being validated.
     * @return bool Returns true if {@link $value} is valid or false otherwise.
     */
    private function validateTimestamp(&$value, array $field, Validation $validation, $name) {
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
     * Validate a value against an enum.
     *
     * @param mixed $value The value to test.
     * @param array $field The field definition of the value.
     * @param Validation $validation The validation object for adding errors.
     * @param string $name The path to the value.
     */
    private function validateEnum($value, array $field, Validation $validation, $name) {
        if (empty($field['enum'])) {
            return;
        }

        $enum = $field['enum'];
        if (!in_array($value, $enum, true)) {
            $validation->addError(
                $name,
                'invalid',
                [
                    'messageCode' => '{field} must be one of: {enum}.',
                    'enum' => $enum,
                    'status' => 422
                ]
            );
        }

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
        array_walk_recursive($result, function (&$value) {
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

    /**
     * Get the class that's used to contain validation information.
     *
     * @return Validation|string Returns the validation class.
     */
    public function getValidationClass() {
        return $this->validationClass;
    }

    /**
     * Set the class that's used to contain validation information.
     *
     * @param Validation|string $class Either the name of a class or a class that will be cloned.
     * @return $this
     */
    public function setValidationClass($class) {
        if (!is_a($class, Validation::class, true)) {
            throw new \InvalidArgumentException("$class must be a subclass of ".Validation::class, 500);
        }

        $this->validationClass = $class;
        return $this;
    }

    /**
     * Create a new validation instance.
     *
     * @return Validation Returns a validation object.
     */
    protected function createValidation() {
        $class = $this->getValidationClass();

        if ($class instanceof Validation) {
            $result = clone $class;
        } else {
            $result = new $class;
        }
        return $result;
    }
}
