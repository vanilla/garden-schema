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
    /**
     * Trigger a notice when extraneous properties are encountered during validation.
     */
    const VALIDATE_EXTRA_PROPERTY_NOTICE = 0x1;

    /**
     * Throw a ValidationException when extraneous properties are encountered during validation.
     */
    const VALIDATE_EXTRA_PROPERTY_EXCEPTION = 0x2;

    /**
     * @var array All the known types.
     *
     * If this is ever given some sort of public access then remove the static.
     */
    private static $types = [
        'array' => ['a'],
        'object' => ['o'],
        'integer' => ['i', 'int'],
        'string' => ['s', 'str'],
        'number' => ['f', 'float'],
        'boolean' => ['b', 'bool'],
        'timestamp' => ['ts'],
        'datetime' => ['dt']
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

        $fn($this->schema, $schema->getSchemaArray());
    }

    /**
     * Returns the internal schema array.
     *
     * @return array
     * @see Schema::jsonSerialize()
     */
    public function getSchemaArray() {
        return $this->schema;
    }

    /**
     * Parse a schema in short form into a full schema array.
     *
     * @param array $arr The array to parse into a schema.
     * @return array The full schema array.
     * @throws \InvalidArgumentException Throws an exception when an item in the schema is invalid.
     */
    protected function parse(array $arr) {
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
        } elseif ($value === null) {
            // Parse child elements.
            if ($node['type'] === 'array' && isset($node['items'])) {
                // The value includes array schema information.
                $node['items'] = $this->parse($node['items']);
            } elseif ($node['type'] === 'object' && isset($node['properties'])) {
                list($node['properties']) = $this->parseProperties($node['properties']);

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
        $type = !empty($parts[1]) ? $this->getType($parts[1]) : null;

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
            function ($data, ValidationField $field) use ($required, $count) {
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

                $field->addError('missingField', [
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
        $validation = new ValidationField($this->createValidation(), $this->schema, '');

        $clean = $this->validateField($data, $validation, $sparse);

        if (!$validation->getValidation()->isValid()) {
            throw new ValidationException($validation->getValidation());
        }

        return $clean;
    }

    /**
     * Validate data against the schema and return the result.
     *
     * @param mixed $data The data to validate.
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
     * @param ValidationField $field A validation object to add errors to.
     * @param bool $sparse Whether or not this is a sparse validation.
     * @return mixed|Invalid Returns a clean version of the value with all extra fields stripped out or invalid if the value
     * is completely invalid.
     */
    protected function validateField($value, ValidationField $field, $sparse = false) {
        $result = $value;
        if ($field->getField() instanceof Schema) {
            try {
                $result = $field->getField()->validate($value, $sparse);
            } catch (ValidationException $ex) {
                // The validation failed, so merge the validations together.
                $field->getValidation()->merge($ex->getValidation(), $field->getName());
            }
        } else {
            // Validate the field's type.
            $type = $field->getType();
            switch ($type) {
                case 'boolean':
                    $result = $this->validateBoolean($value, $field);
                    break;
                case 'integer':
                    $result = $this->validateInteger($value, $field);
                    break;
                case 'number':
                    $result = $this->validateNumber($value, $field);
                    break;
                case 'string':
                    $result = $this->validateString($value, $field);
                    break;
                case 'timestamp':
                    $result = $this->validateTimestamp($value, $field);
                    break;
                case 'datetime':
                    $result = $this->validateDatetime($value, $field);
                    break;
                case 'array':
                    $result = $this->validateArray($value, $field, $sparse);
                    break;
                case 'object':
                    $result = $this->validateObject($value, $field, $sparse);
                    break;
                case null:
                    // No type was specified so we are valid.
                    $result = $value;
                    break;
                default:
                    throw new \InvalidArgumentException("Unrecognized type $type.", 500);
            }
            if (Invalid::isValid($result)) {
                $result = $this->validateEnum($result, $field);
            }
        }

        // Validate a custom field validator.
        if (Invalid::isValid($result)) {
            $this->callValidators($result, $field);
        }

        return $result;
    }

    /**
     * Validate an array.
     *
     * @param mixed $value The value to validate.
     * @param ValidationField $field The validation results to add.
     * @param bool $sparse Whether or not this is a sparse validation.
     * @return array|Invalid Returns an array or invalid if validation fails.
     */
    protected function validateArray($value, ValidationField $field, $sparse = false) {
        if (!is_array($value) || (count($value) > 0 && !array_key_exists(0, $value))) {
            $field->addTypeError('array');
            return Invalid::value();
        } elseif (empty($value)) {
            return [];
        } elseif ($field->val('items') !== null) {
            $result = [];

            // Validate each of the types.
            $itemValidation = new ValidationField(
                $field->getValidation(),
                $field->val('items'),
                ''
            );

            foreach ($value as $i => &$item) {
                $itemValidation->setName($field->getName()."[{$i}]");
                $validItem = $this->validateField($item, $itemValidation, $sparse);
                if (Invalid::isValid($validItem)) {
                    $result[] = $validItem;
                }
            }
        } else {
            // Cast the items into a proper numeric array.
            $result = array_values($value);
        }

        return empty($result) ? Invalid::value() : $result;
    }

    /**
     * Validate a boolean value.
     *
     * @param mixed $value The value to validate.
     * @param ValidationField $field The validation results to add.
     * @return bool|Invalid Returns the cleaned value or invalid if validation fails.
     */
    protected function validateBoolean($value, ValidationField $field) {
        $value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($value === null) {
            $field->addTypeError('boolean');
            return Invalid::value();
        }
        return $value;
    }

    /**
     * Validate a date time.
     *
     * @param mixed $value The value to validate.
     * @param ValidationField $field The validation results to add.
     * @return \DateTimeInterface|Invalid Returns the cleaned value or **null** if it isn't valid.
     */
    protected function validateDatetime($value, ValidationField $field) {
        if ($value instanceof \DateTimeInterface) {
            // do nothing, we're good
        } elseif (is_string($value) && $value !== '') {
            try {
                $dt = new \DateTimeImmutable($value);
                if ($dt) {
                    $value = $dt;
                } else {
                    $value = null;
                }
            } catch (\Exception $ex) {
                $value = Invalid::value();
            }
        } elseif (is_int($value) && $value > 0) {
            $value = new \DateTimeImmutable('@'.(string)round($value));
        } else {
            $value = Invalid::value();
        }

        if (Invalid::isInvalid($value)) {
            $field->addTypeError('datetime');
        }
        return $value;
    }

    /**
     * Validate a float.
     *
     * @param mixed $value The value to validate.
     * @param ValidationField $field The validation results to add.
     * @return float|Invalid Returns a number or **null** if validation fails.
     */
    protected function validateNumber($value, ValidationField $field) {
        $result = filter_var($value, FILTER_VALIDATE_FLOAT);
        if ($result === false) {
            $field->addTypeError('number');
            return Invalid::value();
        }
        return $result;
    }

    /**
     * Validate and integer.
     *
     * @param mixed $value The value to validate.
     * @param ValidationField $field The validation results to add.
     * @return int|Invalid Returns the cleaned value or **null** if validation fails.
     */
    protected function validateInteger($value, ValidationField $field) {
        $result = filter_var($value, FILTER_VALIDATE_INT);

        if ($result === false) {
            $field->addTypeError('integer');
            return Invalid::value();
        }
        return $result;
    }

    /**
     * Validate an object.
     *
     * @param mixed $value The value to validate.
     * @param ValidationField $field The validation results to add.
     * @param bool $sparse Whether or not this is a sparse validation.
     * @return object|Invalid Returns a clean object or **null** if validation fails.
     */
    protected function validateObject($value, ValidationField $field, $sparse = false) {
        if (!is_array($value) || isset($value[0])) {
            $field->addTypeError('object');
            return Invalid::value();
        } elseif (is_array($field->val('properties'))) {
            // Validate the data against the internal schema.
            $value = $this->validateProperties($value, $field, $sparse);
        }
        return $value;
    }

    /**
     * Validate data against the schema and return the result.
     *
     * @param array $data The data to validate.
     * @param ValidationField $field This argument will be filled with the validation result.
     * @param bool $sparse Whether or not this is a sparse validation.
     * @return array|Invalid Returns a clean array with only the appropriate properties and the data coerced to proper types.
     * or invalid if there are no valid properties.
     */
    protected function validateProperties(array $data, ValidationField $field, $sparse = false) {
        $properties = $field->val('properties', []);
        $required = array_flip($field->val('required', []));
        $keys = array_keys($data);
        $keys = array_combine(array_map('strtolower', $keys), $keys);

        $propertyField = new ValidationField($field->getValidation(), [], null);

        // Loop through the schema fields and validate each one.
        $clean = [];
        foreach ($properties as $propertyName => $property) {
            $propertyField
                ->setField($property)
                ->setName(ltrim($field->getName().".$propertyName", '.'));

            $lName = strtolower($propertyName);
            $isRequired = isset($required[$propertyName]);

            // First check for required fields.
            if (!array_key_exists($lName, $keys)) {
                // A sparse validation can leave required fields out.
                if ($isRequired && !$sparse) {
                    $propertyField->addError('missingField', ['messageCode' => '{field} is required.']);
                }
            } elseif ($data[$keys[$lName]] === null) {
                if ($isRequired) {
                    $propertyField->addError('missingField', ['messageCode' => '{field} cannot be null.']);
                } else {
                    $clean[$propertyName] = null;
                }
            } else {
                $clean[$propertyName] = $this->validateField($data[$keys[$lName]], $propertyField, $sparse);
            }

            unset($keys[$lName]);
        }

        // Look for extraneous properties.
        if (!empty($keys)) {
            if ($this->hasFlag(Schema::VALIDATE_EXTRA_PROPERTY_NOTICE)) {
                $msg = sprintf("%s has unexpected field(s): %s.", $field->getName() ?: 'value', implode(', ', $keys));
                trigger_error($msg, E_USER_NOTICE);
            }

            if ($this->hasFlag(Schema::VALIDATE_EXTRA_PROPERTY_EXCEPTION)) {
                $field->addError('invalid', [
                    'messageCode' => '{field} has {extra,plural,an unexpected field,unexpected fields}: {extra}.',
                    'extra' => array_values($keys),
                    'status' => 422
                ]);
            }
        }

        return empty($clean) ? Invalid::value() : $clean;
    }

    /**
     * Validate a string.
     *
     * @param mixed $value The value to validate.
     * @param ValidationField $field The validation results to add.
     * @return string|Invalid Returns the valid string or **null** if validation fails.
     */
    protected function validateString($value, ValidationField $field) {
        if (is_string($value) || is_numeric($value)) {
            $value = $result = (string)$value;
        } else {
            $field->addTypeError('string');
            return Invalid::value();
        }

        $errorCount = $field->getErrorCount();
        if (($minLength = $field->val('minLength', 0)) > 0 && mb_strlen($value) < $minLength) {
            if (!empty($field->getName()) && $minLength === 1) {
                $field->addError('missingField', ['messageCode' => '{field} is required.', 'status' => 422]);
            } else {
                $field->addError(
                    'minLength',
                    [
                        'messageCode' => '{field} should be at least {minLength} {minLength,plural,character} long.',
                        'minLength' => $minLength,
                        'status' => 422
                    ]
                );
            }
        }
        if (($maxLength = $field->val('maxLength', 0)) > 0 && mb_strlen($value) > $maxLength) {
            $field->addError(
                'maxLength',
                [
                    'messageCode' => '{field} is {overflow} {overflow,plural,characters} too long.',
                    'maxLength' => $maxLength,
                    'overflow' => mb_strlen($value) - $maxLength,
                    'status' => 422
                ]
            );
        }
        if ($pattern = $field->val('pattern')) {
            $regex = '`'.str_replace('`', preg_quote('`', '`'), $pattern).'`';

            if (!preg_match($regex, $value)) {
                $field->addError(
                    'invalid',
                    [
                        'messageCode' => '{field} is in the incorrect format.',
                        'status' => 422
                    ]
                );
            }
        }
        if ($format = $field->val('format')) {
            $type = $format;
            switch ($format) {
                case 'date-time':
                    $result = $this->validateDatetime($result, $field);
                    if ($result instanceof \DateTimeInterface) {
                        $result = $result->format(\DateTime::RFC3339);
                    }
                    break;
                case 'email':
                    $result = filter_var($result, FILTER_VALIDATE_EMAIL);
                    break;
                case 'ipv4':
                    $type = 'IPv4 address';
                    $result = filter_var($result, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
                    break;
                case 'ipv6':
                    $type = 'IPv6 address';
                    $result = filter_var($result, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
                    break;
                case 'ip':
                    $type = 'IP address';
                    $result = filter_var($result, FILTER_VALIDATE_IP);
                    break;
                case 'uri':
                    $type = 'URI';
                    $result = filter_var($result, FILTER_VALIDATE_URL, FILTER_FLAG_HOST_REQUIRED | FILTER_FLAG_SCHEME_REQUIRED);
                    break;
                default:
                    trigger_error("Unrecognized format '$format'.", E_USER_NOTICE);
            }
            if ($result === false) {
                $field->addTypeError($type);
            }
        }

        if ($field->isValid()) {
            return $result;
        } else {
            return Invalid::value();
        }
    }

    /**
     * Validate a unix timestamp.
     *
     * @param mixed $value The value to validate.
     * @param ValidationField $field The field being validated.
     * @return int|null Returns a valid timestamp or **null** if the value doesn't validate.
     */
    protected function validateTimestamp($value, ValidationField $field) {
        if (is_numeric($value) && $value > 0) {
            $result = (int)$value;
        } elseif (is_string($value) && $ts = strtotime($value)) {
            $result = $ts;
        } else {
            $field->addTypeError('timestamp');
            $result = null;
        }
        return $result;
    }

    /**
     * Validate a value against an enum.
     *
     * @param mixed $value The value to test.
     * @param ValidationField $field The validation object for adding errors.
     * @return mixed|Invalid Returns the value if it is one of the enumerated values or invalid otherwise.
     */
    protected function validateEnum($value, ValidationField $field) {
        $enum = $field->val('enum');
        if (empty($enum)) {
            return $value;
        }

        if (!in_array($value, $enum, true)) {
            $field->addError(
                'invalid',
                [
                    'messageCode' => '{field} must be one of: {enum}.',
                    'enum' => $enum,
                    'status' => 422
                ]
            );
            return Invalid::value();
        }
        return $value;
    }

    /**
     * Call all of the validators attached to a field.
     *
     * @param mixed $value The field value being validated.
     * @param ValidationField $field The validation object to add errors.
     */
    protected function callValidators($value, ValidationField $field) {
        $valid = true;

        // Strip array references in the name except for the last one.
        $key = preg_replace(['`\[\d+\]$`', '`\[\d+\]`'], ['[]', ''], $field->getName());
        if (!empty($this->validators[$key])) {
            foreach ($this->validators[$key] as $validator) {
                $r = call_user_func($validator, $value, $field);

                if ($r === false || Invalid::isInvalid($r)) {
                    $valid = false;
                }
            }
        }

        // Add an error on the field if the validator hasn't done so.
        if (!$valid && $field->isValid()) {
            $field->addError('invalid', ['messageCode' => '{field} is invalid.', 'status' => 422]);
        }
    }

    /**
     * Specify data which should be serialized to JSON.
     *
     * This method specifically returns data compatible with the JSON schema format.
     *
     * @return mixed Returns data which can be serialized by **json_encode()**, which is a value of any type other than a resource.
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @link http://json-schema.org/
     */
    public function jsonSerialize() {
        $fix = function ($schema) use (&$fix) {
            if ($schema instanceof Schema) {
                return $schema->jsonSerialize();
            }

            if (!empty($schema['type'])) {
                // Swap datetime and timestamp to other types with formats.
                if ($schema['type'] === 'datetime') {
                    $schema['type'] = 'string';
                    $schema['format'] = 'date-time';
                } elseif ($schema['type'] === 'timestamp') {
                    $schema['type'] = 'integer';
                    $schema['format'] = 'timestamp';
                }
            }

            if (!empty($schema['items'])) {
                $schema['items'] = $fix($schema['items']);
            }
            if (!empty($schema['properties'])) {
                $properties = [];
                foreach ($schema['properties'] as $key => $property) {
                    $properties[$key] = $fix($property);
                }
                $schema['properties'] = $properties;
            }

            return $schema;
        };

        $result = $fix($this->schema);

        return $result;
    }

    /**
     * Look up a type based on its alias.
     *
     * @param string $alias The type alias or type name to lookup.
     * @return mixed
     */
    protected function getType($alias) {
        if (isset(self::$types[$alias])) {
            return $alias;
        }
        foreach (self::$types as $type => $aliases) {
            if (in_array($alias, $aliases, true)) {
                return $type;
            }
        }
        return null;
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
