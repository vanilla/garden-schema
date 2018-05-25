<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema;

/**
 * A class for defining and validating data schemas.
 */
class Schema implements \JsonSerializable, \ArrayAccess {
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
        'datetime' => ['dt'],
        'null' => ['n']
    ];

    /**
     * @var string The regular expression to strictly determine if a string is a date.
     */
    private static $DATE_REGEX = '`^\d{4}-\d{2}-\d{2}([ T]\d{2}:\d{2}(:\d{2})?)?`i';

    private $schema = [];

    /**
     * @var int A bitwise combination of the various **Schema::FLAG_*** constants.
     */
    private $flags = 0;

    /**
     * @var array An array of callbacks that will filter data in the schema.
     */
    private $filters = [];

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
        $this->schema = $schema;
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
     * Get a schema field.
     *
     * @param string|array $path The JSON schema path of the field with parts separated by dots.
     * @param mixed $default The value to return if the field isn't found.
     * @return mixed Returns the field value or `$default`.
     */
    public function getField($path, $default = null) {
        if (is_string($path)) {
            $path = explode('.', $path);
        }

        $value = $this->schema;
        foreach ($path as $i => $subKey) {
            if (is_array($value) && isset($value[$subKey])) {
                $value = $value[$subKey];
            } elseif ($value instanceof Schema) {
                return $value->getField(array_slice($path, $i), $default);
            } else {
                return $default;
            }
        }
        return $value;
    }

    /**
     * Set a schema field.
     *
     * @param string|array $path The JSON schema path of the field with parts separated by dots.
     * @param mixed $value The new value.
     * @return $this
     */
    public function setField($path, $value) {
        if (is_string($path)) {
            $path = explode('.', $path);
        }

        $selection = &$this->schema;
        foreach ($path as $i => $subSelector) {
            if (is_array($selection)) {
                if (!isset($selection[$subSelector])) {
                    $selection[$subSelector] = [];
                }
            } elseif ($selection instanceof Schema) {
                $selection->setField(array_slice($path, $i), $value);
                return $this;
            } else {
                $selection = [$subSelector => []];
            }
            $selection = &$selection[$subSelector];
        }

        $selection = $value;
        return $this;
    }

    /**
     * Get the ID for the schema.
     *
     * @return string
     */
    public function getID() {
        return isset($this->schema['id']) ? $this->schema['id'] : '';
    }

    /**
     * Set the ID for the schema.
     *
     * @param string $id The new ID.
     * @throws \InvalidArgumentException Throws an exception when the provided ID is not a string.
     * @return Schema
     */
    public function setID($id) {
        if (is_string($id)) {
            $this->schema['id'] = $id;
        } else {
            throw new \InvalidArgumentException("The ID is not a valid string.", 500);
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
     * @return $this
     */
    public function merge(Schema $schema) {
        $this->mergeInternal($this->schema, $schema->getSchemaArray(), true, true);
        return $this;
    }

    /**
     * Add another schema to this one.
     *
     * Adding schemas together is analogous to array addition. When you add a schema it will only add missing information.
     *
     * @param Schema $schema The schema to add.
     * @param bool $addProperties Whether to add properties that don't exist in this schema.
     * @return $this
     */
    public function add(Schema $schema, $addProperties = false) {
        $this->mergeInternal($this->schema, $schema->getSchemaArray(), false, $addProperties);
        return $this;
    }

    /**
     * The internal implementation of schema merging.
     *
     * @param array &$target The target of the merge.
     * @param array $source The source of the merge.
     * @param bool $overwrite Whether or not to replace values.
     * @param bool $addProperties Whether or not to add object properties to the target.
     * @return array
     */
    private function mergeInternal(array &$target, array $source, $overwrite = true, $addProperties = true) {
        // We need to do a fix for required properties here.
        if (isset($target['properties']) && !empty($source['required'])) {
            $required = isset($target['required']) ? $target['required'] : [];

            if (isset($source['required']) && $addProperties) {
                $newProperties = array_diff(array_keys($source['properties']), array_keys($target['properties']));
                $newRequired = array_intersect($source['required'], $newProperties);

                $required = array_merge($required, $newRequired);
            }
        }


        foreach ($source as $key => $val) {
            if (is_array($val) && array_key_exists($key, $target) && is_array($target[$key])) {
                if ($key === 'properties' && !$addProperties) {
                    // We just want to merge the properties that exist in the destination.
                    foreach ($val as $name => $prop) {
                        if (isset($target[$key][$name])) {
                            $targetProp = &$target[$key][$name];

                            if (is_array($targetProp) && is_array($prop)) {
                                $this->mergeInternal($targetProp, $prop, $overwrite, $addProperties);
                            } elseif (is_array($targetProp) && $prop instanceof Schema) {
                                $this->mergeInternal($targetProp, $prop->getSchemaArray(), $overwrite, $addProperties);
                            } elseif ($overwrite) {
                                $targetProp = $prop;
                            }
                        }
                    }
                } elseif (isset($val[0]) || isset($target[$key][0])) {
                    if ($overwrite) {
                        // This is a numeric array, so just do a merge.
                        $merged = array_merge($target[$key], $val);
                        if (is_string($merged[0])) {
                            $merged = array_keys(array_flip($merged));
                        }
                        $target[$key] = $merged;
                    }
                } else {
                    $target[$key] = $this->mergeInternal($target[$key], $val, $overwrite, $addProperties);
                }
            } elseif (!$overwrite && array_key_exists($key, $target) && !is_array($val)) {
                // Do nothing, we aren't replacing.
            } else {
                $target[$key] = $val;
            }
        }

        if (isset($required)) {
            if (empty($required)) {
                unset($target['required']);
            } else {
                $target['required'] = $required;
            }
        }

        return $target;
    }

//    public function overlay(Schema $schema )

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
     * Parse a short schema and return the associated schema.
     *
     * @param array $arr The schema array.
     * @param mixed ...$args Constructor arguments for the schema instance.
     * @return static Returns a new schema.
     */
    public static function parse(array $arr, ...$args) {
        $schema = new static([], ...$args);
        $schema->schema = $schema->parseInternal($arr);
        return $schema;
    }

    /**
     * Parse a schema in short form into a full schema array.
     *
     * @param array $arr The array to parse into a schema.
     * @return array The full schema array.
     * @throws \InvalidArgumentException Throws an exception when an item in the schema is invalid.
     */
    protected function parseInternal(array $arr) {
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
                        $node['items'] = $this->parseInternal($value);
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
                $node['items'] = $this->parseInternal($node['items']);
            } elseif ($node['type'] === 'object' && isset($node['properties'])) {
                list($node['properties']) = $this->parseProperties($node['properties']);

            }
        }

        if (is_array($node)) {
            if (!empty($node['allowNull'])) {
                $node['type'] = array_merge((array)$node['type'], ['null']);
            }
            unset($node['allowNull']);

            if ($node['type'] === null || $node['type'] === []) {
                unset($node['type']);
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
        $types = [];

        if (!empty($parts[1])) {
            $shortTypes = explode('|', $parts[1]);
            foreach ($shortTypes as $alias) {
                $found = $this->getType($alias);
                if ($found === null) {
                    throw new \InvalidArgumentException("Unknown type '$alias'", 500);
                } else {
                    $types[] = $found;
                }
            }
        }

        if ($value instanceof Schema) {
            if (count($types) === 1 && $types[0] === 'array') {
                $param = ['type' => $types[0], 'items' => $value];
            } else {
                $param = $value;
            }
        } elseif (isset($value['type'])) {
            $param = $value;

            if (!empty($types) && $types !== (array)$param['type']) {
                $typesStr = implode('|', $types);
                $paramTypesStr = implode('|', (array)$param['type']);

                throw new \InvalidArgumentException("Type mismatch between $typesStr and {$paramTypesStr} for field $name.", 500);
            }
        } else {
            if (empty($types) && !empty($parts[1])) {
                throw new \InvalidArgumentException("Invalid type {$parts[1]} for field $name.", 500);
            }
            if (empty($types)) {
                $param = ['type' => null];
            } else {
                $param = ['type' => count($types) === 1 ? $types[0] : $types];
            }

            // Parsed required strings have a minimum length of 1.
            if (in_array('string', $types) && !empty($name) && $required && (!isset($value['default']) || $value['default'] !== '')) {
                $param['minLength'] = 1;
            }
        }

        return [$name, $param, $required];
    }

    /**
     * Add a custom filter to change data before validation.
     *
     * @param string $fieldname The name of the field to filter, if any.
     *
     * If you are adding a filter to a deeply nested field then separate the path with dots.
     * @param callable $callback The callback to filter the field.
     * @return $this
     */
    public function addFilter($fieldname, callable $callback) {
        $this->filters[$fieldname][] = $callback;
        return $this;
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
                // This validator does not apply to sparse validation.
                if ($field->isSparse()) {
                    return true;
                }

                $hasCount = 0;
                $flattened = [];

                foreach ($required as $name) {
                    $flattened = array_merge($flattened, (array)$name);

                    if (is_array($name)) {
                        // This is an array of required names. They all must match.
                        $hasCountInner = 0;
                        foreach ($name as $nameInner) {
                            if (array_key_exists($nameInner, $data)) {
                                $hasCountInner++;
                            } else {
                                break;
                            }
                        }
                        if ($hasCountInner >= count($name)) {
                            $hasCount++;
                        }
                    } elseif (array_key_exists($name, $data)) {
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
        $field = new ValidationField($this->createValidation(), $this->schema, '', $sparse);

        $clean = $this->validateField($data, $field, $sparse);

        if (Invalid::isInvalid($clean) && $field->isValid()) {
            // This really shouldn't happen, but we want to protect against seeing the invalid object.
            $field->addError('invalid', ['messageCode' => '{field} is invalid.', 'status' => 422]);
        }

        if (!$field->getValidation()->isValid()) {
            throw new ValidationException($field->getValidation());
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
        $result = $value = $this->filterField($value, $field);

        if ($field->getField() instanceof Schema) {
            try {
                $result = $field->getField()->validate($value, $sparse);
            } catch (ValidationException $ex) {
                // The validation failed, so merge the validations together.
                $field->getValidation()->merge($ex->getValidation(), $field->getName());
            }
        } elseif (($value === null || ($value === '' && !$field->hasType('string'))) && $field->hasType('null')) {
            $result = null;
        } else {
            // Validate the field's type.
            $type = $field->getType();
            if (is_array($type)) {
                $result = $this->validateMultipleTypes($value, $type, $field, $sparse);
            } else {
                $result = $this->validateSingleType($value, $type, $field, $sparse);
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
        if ((!is_array($value) || (count($value) > 0 && !array_key_exists(0, $value))) && !$value instanceof \Traversable) {
            $field->addTypeError('array');
            return Invalid::value();
        } else {
            if ((null !== $minItems = $field->val('minItems')) && count($value) < $minItems) {
                $field->addError(
                    'minItems',
                    [
                        'messageCode' => '{field} must contain at least {minItems} {minItems,plural,item}.',
                        'minItems' => $minItems,
                        'status' => 422
                    ]
                );
            }
            if ((null !== $maxItems = $field->val('maxItems')) && count($value) > $maxItems) {
                $field->addError(
                    'maxItems',
                    [
                        'messageCode' => '{field} must contain no more than {maxItems} {maxItems,plural,item}.',
                        'maxItems' => $maxItems,
                        'status' => 422
                    ]
                );
            }

            if ($field->val('items') !== null) {
                $result = [];

                // Validate each of the types.
                $itemValidation = new ValidationField(
                    $field->getValidation(),
                    $field->val('items'),
                    '',
                    $sparse
                );

                $count = 0;
                foreach ($value as $i => $item) {
                    $itemValidation->setName($field->getName()."[{$i}]");
                    $validItem = $this->validateField($item, $itemValidation, $sparse);
                    if (Invalid::isValid($validItem)) {
                        $result[] = $validItem;
                    }
                    $count++;
                }

                return empty($result) && $count > 0 ? Invalid::value() : $result;
            } else {
                // Cast the items into a proper numeric array.
                $result = is_array($value) ? array_values($value) : iterator_to_array($value);
                return $result;
            }
        }
    }

    /**
     * Validate a boolean value.
     *
     * @param mixed $value The value to validate.
     * @param ValidationField $field The validation results to add.
     * @return bool|Invalid Returns the cleaned value or invalid if validation fails.
     */
    protected function validateBoolean($value, ValidationField $field) {
        $value = $value === null ? $value : filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
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
        } elseif (is_string($value) && $value !== '' && !is_numeric($value)) {
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
        if (!$this->isArray($value) || isset($value[0])) {
            $field->addTypeError('object');
            return Invalid::value();
        } elseif (is_array($field->val('properties'))) {
            // Validate the data against the internal schema.
            $value = $this->validateProperties($value, $field, $sparse);
        } elseif (!is_array($value)) {
            $value = $this->toObjectArray($value);
        }
        return $value;
    }

    /**
     * Validate data against the schema and return the result.
     *
     * @param array|\ArrayAccess $data The data to validate.
     * @param ValidationField $field This argument will be filled with the validation result.
     * @param bool $sparse Whether or not this is a sparse validation.
     * @return array|Invalid Returns a clean array with only the appropriate properties and the data coerced to proper types.
     * or invalid if there are no valid properties.
     */
    protected function validateProperties($data, ValidationField $field, $sparse = false) {
        $properties = $field->val('properties', []);
        $required = array_flip($field->val('required', []));

        if (is_array($data)) {
            $keys = array_keys($data);
            $clean = [];
        } else {
            $keys = array_keys(iterator_to_array($data));
            $class = get_class($data);
            $clean = new $class;

            if ($clean instanceof \ArrayObject) {
                $clean->setFlags($data->getFlags());
                $clean->setIteratorClass($data->getIteratorClass());
            }
        }
        $keys = array_combine(array_map('strtolower', $keys), $keys);

        $propertyField = new ValidationField($field->getValidation(), [], null, $sparse);

        // Loop through the schema fields and validate each one.
        foreach ($properties as $propertyName => $property) {
            $propertyField
                ->setField($property)
                ->setName(ltrim($field->getName().".$propertyName", '.'));

            $lName = strtolower($propertyName);
            $isRequired = isset($required[$propertyName]);

            // First check for required fields.
            if (!array_key_exists($lName, $keys)) {
                if ($sparse) {
                    // Sparse validation can leave required fields out.
                } elseif ($propertyField->hasVal('default')) {
                    $clean[$propertyName] = $propertyField->val('default');
                } elseif ($isRequired) {
                    $propertyField->addError('missingField', ['messageCode' => '{field} is required.']);
                }
            } else {
                $value = $data[$keys[$lName]];

                if (in_array($value, [null, ''], true) && !$isRequired && !$propertyField->hasType('null')) {
                    if ($propertyField->getType() !== 'string' || $value === null) {
                        continue;
                    }
                }

                $clean[$propertyName] = $this->validateField($value, $propertyField, $sparse);
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

        return $clean;
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
     * @return int|Invalid Returns a valid timestamp or invalid if the value doesn't validate.
     */
    protected function validateTimestamp($value, ValidationField $field) {
        if (is_numeric($value) && $value > 0) {
            $result = (int)$value;
        } elseif (is_string($value) && $ts = strtotime($value)) {
            $result = $ts;
        } else {
            $field->addTypeError('timestamp');
            $result = Invalid::value();
        }
        return $result;
    }

    /**
     * Validate a null value.
     *
     * @param mixed $value The value to validate.
     * @param ValidationField $field The error collector for the field.
     * @return null|Invalid Returns **null** or invalid.
     */
    protected function validateNull($value, ValidationField $field) {
        if ($value === null) {
            return null;
        }
        $field->addError('invalid', ['messageCode' => '{field} should be null.', 'status' => 422]);
        return Invalid::value();
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
     * Call all of the filters attached to a field.
     *
     * @param mixed $value The field value being filtered.
     * @param ValidationField $field The validation object.
     * @return mixed Returns the filtered value. If there are no filters for the field then the original value is returned.
     */
    protected function callFilters($value, ValidationField $field) {
        // Strip array references in the name except for the last one.
        $key = preg_replace(['`\[\d+\]$`', '`\[\d+\]`'], ['[]', ''], $field->getName());
        if (!empty($this->filters[$key])) {
            foreach ($this->filters[$key] as $filter) {
                $value = call_user_func($filter, $value, $field);
            }
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
                $types = (array)$schema['type'];

                foreach ($types as $i => &$type) {
                    // Swap datetime and timestamp to other types with formats.
                    if ($type === 'datetime') {
                        $type = 'string';
                        $schema['format'] = 'date-time';
                    } elseif ($schema['type'] === 'timestamp') {
                        $type = 'integer';
                        $schema['format'] = 'timestamp';
                    }
                }
                $types = array_unique($types);
                $schema['type'] = count($types) === 1 ? reset($types) : $types;
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

    /**
     * Check whether or not a value is an array or accessible like an array.
     *
     * @param mixed $value The value to check.
     * @return bool Returns **true** if the value can be used like an array or **false** otherwise.
     */
    private function isArray($value) {
        return is_array($value) || ($value instanceof \ArrayAccess && $value instanceof \Traversable);
    }

    /**
     * Cast a value to an array.
     *
     * @param \Traversable $value The value to convert.
     * @return array Returns an array.
     */
    private function toObjectArray(\Traversable $value) {
        $class = get_class($value);
        if ($value instanceof \ArrayObject) {
            return new $class($value->getArrayCopy(), $value->getFlags(), $value->getIteratorClass());
        } elseif ($value instanceof \ArrayAccess) {
            $r = new $class;
            foreach ($value as $k => $v) {
                $r[$k] = $v;
            }
            return $r;
        }
        return iterator_to_array($value);
    }

    /**
     * Return a sparse version of this schema.
     *
     * A sparse schema has no required properties.
     *
     * @return Schema Returns a new sparse schema.
     */
    public function withSparse() {
        $sparseSchema = $this->withSparseInternal($this, new \SplObjectStorage());
        return $sparseSchema;
    }

    /**
     * The internal implementation of `Schema::withSparse()`.
     *
     * @param array|Schema $schema The schema to make sparse.
     * @param \SplObjectStorage $schemas Collected sparse schemas that have already been made.
     * @return mixed
     */
    private function withSparseInternal($schema, \SplObjectStorage $schemas) {
        if ($schema instanceof Schema) {
            if ($schemas->contains($schema)) {
                return $schemas[$schema];
            } else {
                $schemas[$schema] = $sparseSchema = new Schema();
                $sparseSchema->schema = $schema->withSparseInternal($schema->schema, $schemas);
                if ($id = $sparseSchema->getID()) {
                    $sparseSchema->setID($id.'Sparse');
                }

                return $sparseSchema;
            }
        }

        unset($schema['required']);

        if (isset($schema['items'])) {
            $schema['items'] = $this->withSparseInternal($schema['items'], $schemas);
        }
        if (isset($schema['properties'])) {
            foreach ($schema['properties'] as $name => &$property) {
                $property = $this->withSparseInternal($property, $schemas);
            }
        }

        return $schema;
    }

    /**
     * Filter a field's value using built in and custom filters.
     *
     * @param mixed $value The original value of the field.
     * @param ValidationField $field The field information for the field.
     * @return mixed Returns the filtered field or the original field value if there are no filters.
     */
    private function filterField($value, ValidationField $field) {
        // Check for limited support for Open API style.
        if (!empty($field->val('style')) && is_string($value)) {
            $doFilter = true;
            if ($field->hasType('boolean') && in_array($value, ['true', 'false', '0', '1'], true)) {
                $doFilter = false;
            } elseif ($field->hasType('integer') || $field->hasType('number') && is_numeric($value)) {
                $doFilter = false;
            }

            if ($doFilter) {
                switch ($field->val('style')) {
                    case 'form':
                        $value = explode(',', $value);
                        break;
                    case 'spaceDelimited':
                        $value = explode(' ', $value);
                        break;
                    case 'pipeDelimited':
                        $value = explode('|', $value);
                        break;
                }
            }
        }

        $value = $this->callFilters($value, $field);

        return $value;
    }

    /**
     * Whether a offset exists.
     *
     * @param mixed $offset An offset to check for.
     * @return boolean true on success or false on failure.
     * @link http://php.net/manual/en/arrayaccess.offsetexists.php
     */
    public function offsetExists($offset) {
        return isset($this->schema[$offset]);
    }

    /**
     * Offset to retrieve.
     *
     * @param mixed $offset The offset to retrieve.
     * @return mixed Can return all value types.
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     */
    public function offsetGet($offset) {
        return isset($this->schema[$offset]) ? $this->schema[$offset] : null;
    }

    /**
     * Offset to set.
     *
     * @param mixed $offset The offset to assign the value to.
     * @param mixed $value The value to set.
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     */
    public function offsetSet($offset, $value) {
        $this->schema[$offset] = $value;
    }

    /**
     * Offset to unset.
     *
     * @param mixed $offset The offset to unset.
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     */
    public function offsetUnset($offset) {
        unset($this->schema[$offset]);
    }

    /**
     * Validate a field against a single type.
     *
     * @param mixed $value The value to validate.
     * @param string $type The type to validate against.
     * @param ValidationField $field Contains field and validation information.
     * @param bool $sparse Whether or not this should be a sparse validation.
     * @return mixed Returns the valid value or `Invalid`.
     */
    protected function validateSingleType($value, $type, ValidationField $field, $sparse) {
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
            case 'null':
                $result = $this->validateNull($value, $field);
                break;
            case null:
                // No type was specified so we are valid.
                $result = $value;
                break;
            default:
                throw new \InvalidArgumentException("Unrecognized type $type.", 500);
        }
        return $result;
    }

    /**
     * Validate a field against multiple basic types.
     *
     * The first validation that passes will be returned. If no type can be validated against then validation will fail.
     *
     * @param mixed $value The value to validate.
     * @param string[] $types The types to validate against.
     * @param ValidationField $field Contains field and validation information.
     * @param bool $sparse Whether or not this should be a sparse validation.
     * @return mixed Returns the valid value or `Invalid`.
     */
    private function validateMultipleTypes($value, array $types, ValidationField $field, $sparse) {
        // First check for an exact type match.
        switch (gettype($value)) {
            case 'boolean':
                if (in_array('boolean', $types)) {
                    $singleType = 'boolean';
                }
                break;
            case 'integer':
                if (in_array('integer', $types)) {
                    $singleType = 'integer';
                } elseif (in_array('number', $types)) {
                    $singleType = 'number';
                }
                break;
            case 'double':
                if (in_array('number', $types)) {
                    $singleType = 'number';
                } elseif (in_array('integer', $types)) {
                    $singleType = 'integer';
                }
                break;
            case 'string':
                if (in_array('datetime', $types) && preg_match(self::$DATE_REGEX, $value)) {
                    $singleType = 'datetime';
                } elseif (in_array('string', $types)) {
                    $singleType = 'string';
                }
                break;
            case 'array':
                if (in_array('array', $types) && in_array('object', $types)) {
                    $singleType = isset($value[0]) || empty($value) ? 'array' : 'object';
                } elseif (in_array('object', $types)) {
                    $singleType = 'object';
                } elseif (in_array('array', $types)) {
                    $singleType = 'array';
                }
                break;
            case 'NULL':
                if (in_array('null', $types)) {
                    $singleType = $this->validateSingleType($value, 'null', $field, $sparse);
                }
                break;
        }
        if (!empty($singleType)) {
            return $this->validateSingleType($value, $singleType, $field, $sparse);
        }

        // Clone the validation field to collect errors.
        $typeValidation = new ValidationField(new Validation(), $field->getField(), '', $sparse);

        // Try and validate against each type.
        foreach ($types as $type) {
            $result = $this->validateSingleType($value, $type, $typeValidation, $sparse);
            if (Invalid::isValid($result)) {
                return $result;
            }
        }

        // Since we got here the value is invalid.
        $field->merge($typeValidation->getValidation());
        return Invalid::value();
    }
}
