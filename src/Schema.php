<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2022 Vanilla Forums Inc.
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
     * Validate string lengths as unicode characters instead of bytes.
     */
    const VALIDATE_STRING_LENGTH_AS_UNICODE = 0x4;

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

        // Psuedo-types
        'timestamp' => ['ts'], // type: integer, format: timestamp
        'datetime' => ['dt'], // type: string, format: date-time
        'null' => ['n'], // Adds nullable: true
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
     * @deprecated
     */
    private $validationClass = Validation::class;

    /**
     * @var callable A callback is used to create validation objects.
     */
    private $validationFactory = [Validation::class, 'createValidation'];

    /**
     * @var callable
     */
    private $refLookup;

    /// Methods ///

    /**
     * Initialize an instance of a new {@link Schema} class.
     *
     * @param array $schema The array schema to validate against.
     * @param callable $refLookup The function used to lookup references.
     */
    public function __construct(array $schema = [], callable $refLookup = null) {
        $this->schema = $schema;

        $this->refLookup = $refLookup ?? function (/** @scrutinizer ignore-unused */
                string $_) {
                return null;
            };

        $this->setFlag(self::VALIDATE_STRING_LENGTH_AS_UNICODE, true);
    }

    /**
     * Parse a short schema and return the associated schema.
     *
     * @param array $arr The schema array.
     * @param mixed[] $args Constructor arguments for the schema instance.
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
     * @throws ParseException Throws an exception when an item in the schema is invalid.
     */
    protected function parseInternal(array $arr): array {
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
     * @param array|Schema $node The node to parse.
     * @param mixed $value Additional information from the node.
     * @return array|\ArrayAccess Returns a JSON schema compatible node.
     * @throws ParseException Throws an exception if there was a problem parsing the schema node.
     */
    private function parseNode($node, $value = null) {
        if (is_array($value)) {
            if (is_array($node['type'])) {
                trigger_error('Schemas with multiple types are deprecated.', E_USER_DEPRECATED);
            }

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
                $node['nullable'] = true;
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
     * @throws ParseException Throws an exception if a property name cannot be determined for an array item.
     */
    private function parseProperties(array $arr): array {
        $properties = [];
        $requiredProperties = [];
        foreach ($arr as $key => $value) {
            // Fix a schema specified as just a value.
            if (is_int($key)) {
                if (is_string($value)) {
                    $key = $value;
                    $value = '';
                } else {
                    throw new ParseException("Schema at position $key is not a valid parameter.", 500);
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
        return [$properties, $requiredProperties];
    }

    /**
     * Parse a short parameter string into a full array parameter.
     *
     * @param string $key The short parameter string to parse.
     * @param array $value An array of other information that might help resolve ambiguity.
     * @return array Returns an array in the form `[string name, array param, bool required]`.
     * @throws ParseException Throws an exception if the short param is not in the correct format.
     */
    public function parseShortParam(string $key, $value = []): array {
        // Is the parameter optional?
        if (substr($key, -1) === '?') {
            $required = false;
            $key = substr($key, 0, -1);
        } else {
            $required = true;
        }

        // Check for a type.
        if (false !== ($pos = strrpos($key, ':'))) {
            $name = substr($key, 0, $pos);
            $typeStr = substr($key, $pos + 1);

            // Kludge for names with colons that are not specifying an array of a type.
            if (isset($value['type']) && 'array' !== $this->getType($typeStr)) {
                $name = $key;
                $typeStr = '';
            }
        } else {
            $name = $key;
            $typeStr = '';
        }
        $types = [];
        $param = [];

        if (!empty($typeStr)) {
            $shortTypes = explode('|', $typeStr);
            foreach ($shortTypes as $alias) {
                $found = $this->getType($alias);
                if ($found === null) {
                    throw new ParseException("Unknown type '$alias'.", 500);
                } elseif ($found === 'datetime') {
                    $param['format'] = 'date-time';
                    $types[] = 'string';
                } elseif ($found === 'timestamp') {
                    $param['format'] = 'timestamp';
                    $types[] = 'integer';
                } elseif ($found === 'null') {
                    $nullable = true;
                } else {
                    $types[] = $found;
                }
            }
        }

        if ($value instanceof Schema) {
            if (count($types) === 1 && $types[0] === 'array') {
                $param += ['type' => $types[0], 'items' => $value];
            } else {
                $param = $value;
            }
        } elseif (isset($value['type'])) {
            $param = $value + $param;

            if (!empty($types) && $types !== (array)$param['type']) {
                $typesStr = implode('|', $types);
                $paramTypesStr = implode('|', (array)$param['type']);

                throw new ParseException("Type mismatch between $typesStr and {$paramTypesStr} for field $name.", 500);
            }
        } else {
            if (empty($types) && !empty($parts[1])) {
                throw new ParseException("Invalid type {$parts[1]} for field $name.", 500);
            }
            if (empty($types)) {
                $param += ['type' => null];
            } else {
                $param += ['type' => count($types) === 1 ? $types[0] : $types];
            }

            // Parsed required strings have a minimum length of 1.
            if (in_array('string', $types) && !empty($name) && $required && (!isset($value['default']) || $value['default'] !== '')) {
                $param['minLength'] = 1;
            }
        }

        if (!empty($nullable)) {
            $param['nullable'] = true;
        }

        if (is_array($param['type'])) {
            trigger_error('Schemas with multiple types is deprecated.', E_USER_DEPRECATED);
        }

        return [$name, $param, $required];
    }

    /**
     * Look up a type based on its alias.
     *
     * @param string $alias The type alias or type name to lookup.
     * @return mixed
     */
    private function getType($alias) {
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
     * Unescape a JSON reference segment.
     *
     * @param string $str The segment to unescapeRef.
     * @return string Returns the unescaped string.
     */
    public static function unescapeRef(string $str): string {
        return str_replace(['~1', '~0'], ['/', '~'], $str);
    }

    /**
     * Explode a references into its individual parts.
     *
     * @param string $ref A JSON reference.
     * @return string[] The individual parts of the reference.
     */
    public static function explodeRef(string $ref): array {
        return array_map([self::class, 'unescapeRef'], explode('/', $ref));
    }

    /**
     * Grab the schema's current description.
     *
     * @return string
     */
    public function getDescription(): string {
        return $this->schema['description'] ?? '';
    }

    /**
     * Set the description for the schema.
     *
     * @param string $description The new description.
     * @return $this
     */
    public function setDescription(string $description) {
        $this->schema['description'] = $description;
        return $this;
    }

    /**
     * Get the schema's title.
     *
     * @return string Returns the title.
     */
    public function getTitle(): string {
        return $this->schema['title'] ?? '';
    }

    /**
     * Set the schema's title.
     *
     * @param string $title The new title.
     */
    public function setTitle(string $title) {
        $this->schema['title'] = $title;
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
            if (strpos($path, '.') !== false && strpos($path, '/') === false) {
                trigger_error('Field selectors must be separated by "/" instead of "."', E_USER_DEPRECATED);
                $path = explode('.', $path);
            } else {
                $path = explode('/', $path);
            }
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
     * @param string|array $path The JSON schema path of the field with parts separated by slashes.
     * @param mixed $value The new value.
     * @return $this
     */
    public function setField($path, $value) {
        if (is_string($path)) {
            if (strpos($path, '.') !== false && strpos($path, '/') === false) {
                trigger_error('Field selectors must be separated by "/" instead of "."', E_USER_DEPRECATED);
                $path = explode('.', $path);
            } else {
                $path = explode('/', $path);
            }
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
     * Return the validation flags.
     *
     * @return int Returns a bitwise combination of flags.
     */
    public function getFlags(): int {
        return $this->flags;
    }

    /**
     * Set the validation flags.
     *
     * @param int $flags One or more of the **Schema::FLAG_*** constants.
     * @return Schema Returns the current instance for fluent calls.
     */
    public function setFlags(int $flags) {
        $this->flags = $flags;

        return $this;
    }

    /**
     * Set a flag.
     *
     * @param int $flag One or more of the **Schema::VALIDATE_*** constants.
     * @param bool $value Either true or false.
     * @return $this
     */
    public function setFlag(int $flag, bool $value) {
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
     * The internal implementation of schema merging.
     *
     * @param array $target The target of the merge.
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

    /**
     * Returns the internal schema array.
     *
     * @return array
     * @see Schema::jsonSerialize()
     */
    public function getSchemaArray(): array {
        return $this->schema;
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
     * Add a custom filter to change data before validation.
     *
     * @param string $fieldname The name of the field to filter, if any.
     *
     * If you are adding a filter to a deeply nested field then separate the path with dots.
     * @param callable $callback The callback to filter the field.
     * @param bool $validate Whether or not the filter should also validate. If true default validation is skipped.
     * @return $this
     */
    public function addFilter(string $fieldname, callable $callback, bool $validate = false) {
        $fieldname = $this->parseFieldSelector($fieldname);
        $this->filters[$fieldname][] = [$callback, $validate];
        return $this;
    }

    /**
     * Parse a nested field name selector.
     *
     * Field selectors should be separated by "/" characters, but may currently be separated by "." characters which
     * triggers a deprecated error.
     *
     * @param string $field The field selector.
     * @return string Returns the field selector in the correct format.
     */
    private function parseFieldSelector(string $field): string {
        if (strlen($field) === 0) {
            return $field;
        }

        if (strpos($field, '.') !== false) {
            if (strpos($field, '/') === false) {
                trigger_error('Field selectors must be separated by "/" instead of "."', E_USER_DEPRECATED);

                $parts = explode('.', $field);
                $parts = @array_map([$this, 'parseFieldSelector'], $parts); // silence because error triggered already.

                $field = implode('/', $parts);
            }
        } elseif ($field === '[]') {
            trigger_error('Field selectors with item selector "[]" must be converted to "items".', E_USER_DEPRECATED);
            $field = 'items';
        } elseif (strpos($field, '/') === false && !in_array($field, ['items', 'additionalProperties'], true)) {
            trigger_error("Field selectors must specify full schema paths. ($field)", E_USER_DEPRECATED);
            $field = "/properties/$field";
        }

        if (strpos($field, '[]') !== false) {
            trigger_error('Field selectors with item selector "[]" must be converted to "/items".', E_USER_DEPRECATED);
            $field = str_replace('[]', '/items', $field);
        }

        return ltrim($field, '/');
    }

    /**
     * Add a custom filter for a schema format.
     *
     * Schemas can use the `format` property to specify a specific format on a field. Adding a filter for a format
     * allows you to customize the behavior of that format.
     *
     * @param string $format The format to filter.
     * @param callable $callback The callback used to filter values.
     * @param bool $validate Whether or not the filter should also validate. If true default validation is skipped.
     * @return $this
     */
    public function addFormatFilter(string $format, callable $callback, bool $validate = false) {
        if (empty($format)) {
            throw new \InvalidArgumentException('The filter format cannot be empty.', 500);
        }

        $filter = "/format/$format";
        $this->filters[$filter][] = [$callback, $validate];

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
    public function requireOneOf(array $required, string $fieldname = '', int $count = 1) {
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
                    $message = 'One of {properties} are required.';
                } else {
                    $message = '{count} of {properties} are required.';
                }

                $field->addError('oneOfRequired', [
                    'messageCode' => $message,
                    'properties' => $required,
                    'count' => $count
                ]);
                return false;
            }
        );

        return $result;
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
    public function addValidator(string $fieldname, callable $callback) {
        $fieldname = $this->parseFieldSelector($fieldname);
        $this->validators[$fieldname][] = $callback;
        return $this;
    }

    /**
     * Validate data against the schema and return the result.
     *
     * @param mixed $data The data to validate.
     * @param array $options Validation options. See `Schema::validate()`.
     * @return bool Returns true if the data is valid. False otherwise.
     * @throws RefNotFoundException Throws an exception when there is an unknown `$ref` in the schema.
     */
    public function isValid($data, $options = []) {
        try {
            $this->validate($data, $options);
            return true;
        } catch (ValidationException $ex) {
            return false;
        }
    }

    /**
     * Validate data against the schema.
     *
     * @param mixed $data The data to validate.
     * @param array $options Validation options.
     *
     * - **sparse**: Whether or not this is a sparse validation.
     * @return mixed Returns a cleaned version of the data.
     * @throws ValidationException Throws an exception when the data does not validate against the schema.
     * @throws RefNotFoundException Throws an exception when a schema `$ref` is not found.
     */
    public function validate($data, $options = []) {
        if (is_bool($options)) {
            trigger_error('The $sparse parameter is deprecated. Use [\'sparse\' => true] instead.', E_USER_DEPRECATED);
            $options = ['sparse' => true];
        }
        $options += ['sparse' => false];


        list($schema, $schemaPath) = $this->lookupSchema($this->schema, '');
        $field = new ValidationField($this->createValidation(), $schema, '', $schemaPath, $options);

        $clean = $this->validateField($data, $field);

        if (Invalid::isInvalid($clean) && $field->isValid()) {
            // This really shouldn't happen, but we want to protect against seeing the invalid object.
            $field->addError('invalid', ['messageCode' => 'The value is invalid.']);
        }

        if (!$field->getValidation()->isValid()) {
            throw new ValidationException($field->getValidation());
        }

        return $clean;
    }

    /**
     * Lookup a schema based on a schema node.
     *
     * The node could be a schema array, `Schema` object, or a schema reference.
     *
     * @param mixed $schema The schema node to lookup with.
     * @param string $schemaPath The current path of the schema.
     * @return array Returns an array with two elements:
     * - Schema|array|\ArrayAccess The schema that was found.
     * - string The path of the schema. This is either the reference or the `$path` parameter for inline schemas.
     * @throws RefNotFoundException Throws an exception when a reference could not be found.
     */
    private function lookupSchema($schema, string $schemaPath) {
        if ($schema instanceof Schema) {
            return [$schema, $schemaPath];
        } else {
            $lookup = $this->getRefLookup();
            $visited = [];

            // Resolve any references first.
            while (!empty($schema['$ref'])) {
                $schemaPath = $schema['$ref'];

                if (isset($visited[$schemaPath])) {
                    throw new RefNotFoundException("Cyclical reference cannot be resolved. ($schemaPath)", 508);
                }
                $visited[$schemaPath] = true;

                try {
                    $schema = call_user_func($lookup, $schemaPath);
                } catch (\Exception $ex) {
                    throw new RefNotFoundException($ex->getMessage(), $ex->getCode(), $ex);
                }
                if ($schema === null) {
                    throw new RefNotFoundException("Schema reference could not be found. ($schemaPath)");
                }
            }

            return [$schema, $schemaPath];
        }
    }

    /**
     * Get the function used to resolve `$ref` lookups.
     *
     * @return callable Returns the current `$ref` lookup.
     */
    public function getRefLookup(): callable {
        return $this->refLookup;
    }

    /**
     * Set the function used to resolve `$ref` lookups.
     *
     * The function should have the following signature:
     *
     * ```php
     * function(string $ref): array|Schema|null {
     *     ...
     * }
     * ```
     * The function should take a string reference and return a schema array, `Schema` or **null**.
     *
     * @param callable $refLookup The new lookup function.
     * @return $this
     */
    public function setRefLookup(callable $refLookup) {
        $this->refLookup = $refLookup;
        return $this;
    }

    /**
     * Create a new validation instance.
     *
     * @return Validation Returns a validation object.
     */
    protected function createValidation(): Validation {
        return call_user_func($this->getValidationFactory());
    }

    /**
     * Get factory used to create validation objects.
     *
     * @return callable Returns the current factory.
     */
    public function getValidationFactory(): callable {
        return $this->validationFactory;
    }

    /**
     * Set the factory used to create validation objects.
     *
     * @param callable $validationFactory The new factory.
     * @return $this
     */
    public function setValidationFactory(callable $validationFactory) {
        $this->validationFactory = $validationFactory;
        $this->validationClass = null;
        return $this;
    }

    /**
     * Validate a field.
     *
     * @param mixed $value The value to validate.
     * @param ValidationField $field A validation object to add errors to.
     * @return mixed|Invalid Returns a clean version of the value with all extra fields stripped out or invalid if the value
     * is completely invalid.
     * @throws RefNotFoundException Throws an exception when a schema `$ref` is not found.
     */
    protected function validateField($value, ValidationField $field) {
        $validated = false;
        $result = $value = $this->filterField($value, $field, $validated);

        if ($validated) {
            return $result;
        } elseif ($field->getField() instanceof Schema) {
            try {
                $result = $field->getField()->validate($value, $field->getOptions());
            } catch (ValidationException $ex) {
                // The validation failed, so merge the validations together.
                $field->getValidation()->merge($ex->getValidation(), $field->getName());
            }
        } elseif (($value === null || ($value === '' && !$field->hasType('string'))) && ($field->val('nullable') || $field->hasType('null'))) {
            $result = null;
        } else {
            // Look for a discriminator.
            if (!empty($field->val('discriminator'))) {
                $field = $this->resolveDiscriminator($value, $field);
            }

            if ($field !== null) {
                if($field->hasAllOf()) {
                    $result = $this->validateAllOf($value, $field);
                } else {
                    // Validate the field's type.
                    $type = $field->getType();
                    if (is_array($type)) {
                        $result = $this->validateMultipleTypes($value, $type, $field);
                    } else {
                        $result = $this->validateSingleType($value, $type, $field);
                    }

                    if (Invalid::isValid($result)) {
                        $result = $this->validateEnum($result, $field);
                    }
                }
            } else {
                $result = Invalid::value();
            }
        }

        // Validate a custom field validator.
        if (Invalid::isValid($result)) {
            $this->callValidators($result, $field);
        }

        return $result;
    }

    /**
     * Filter a field's value using built in and custom filters.
     *
     * @param mixed $value The original value of the field.
     * @param ValidationField $field The field information for the field.
     * @param bool $validated Whether or not a filter validated the value.
     * @return mixed Returns the filtered field or the original field value if there are no filters.
     */
    private function filterField($value, ValidationField $field, bool &$validated = false) {
        // Check for limited support for Open API style.
        if (!empty($field->val('style')) && is_string($value)) {
            $doFilter = true;
            if ($field->hasType('boolean') && in_array($value, ['true', 'false', '0', '1'], true)) {
                $doFilter = false;
            } elseif (($field->hasType('integer') || $field->hasType('number')) && is_numeric($value)) {
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

        $value = $this->callFilters($value, $field, $validated);

        return $value;
    }

    /**
     * Call all of the filters attached to a field.
     *
     * @param mixed $value The field value being filtered.
     * @param ValidationField $field The validation object.
     * @param bool $validated Whether or not a filter validated the field.
     * @return mixed Returns the filtered value. If there are no filters for the field then the original value is returned.
     */
    private function callFilters($value, ValidationField $field, bool &$validated = false) {
        // Strip array references in the name except for the last one.
        $key = $field->getSchemaPath();
        if (!empty($this->filters[$key])) {
            foreach ($this->filters[$key] as list($filter, $validate)) {
                $value = call_user_func($filter, $value, $field);
                $validated |= $validate;

                if (Invalid::isInvalid($value)) {
                    return $value;
                }
            }
        }
        $key = '/format/'.$field->val('format');
        if (!empty($this->filters[$key])) {
            foreach ($this->filters[$key] as list($filter, $validate)) {
                $value = call_user_func($filter, $value, $field);
                $validated |= $validate;

                if (Invalid::isInvalid($value)) {
                    return $value;
                }
            }
        }

        return $value;
    }

    /**
     * Validate a field against multiple basic types.
     *
     * The first validation that passes will be returned. If no type can be validated against then validation will fail.
     *
     * @param mixed $value The value to validate.
     * @param string[] $types The types to validate against.
     * @param ValidationField $field Contains field and validation information.
     * @return mixed Returns the valid value or `Invalid`.
     * @throws RefNotFoundException Throws an exception when a schema `$ref` is not found.
     * @deprecated Multiple types are being removed next version.
     */
    private function validateMultipleTypes($value, array $types, ValidationField $field) {
        trigger_error('Multiple schema types are deprecated.', E_USER_DEPRECATED);

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
                    $singleType = $this->validateSingleType($value, 'null', $field);
                }
                break;
        }
        if (!empty($singleType)) {
            return $this->validateSingleType($value, $singleType, $field);
        }

        // Clone the validation field to collect errors.
        $typeValidation = new ValidationField(new Validation(), $field->getField(), '', '', $field->getOptions());

        // Try and validate against each type.
        foreach ($types as $type) {
            $result = $this->validateSingleType($value, $type, $typeValidation);
            if (Invalid::isValid($result)) {
                return $result;
            }
        }

        // Since we got here the value is invalid.
        $field->merge($typeValidation->getValidation());
        return Invalid::value();
    }

    /**
     * Validate a field against a single type.
     *
     * @param mixed $value The value to validate.
     * @param string $type The type to validate against.
     * @param ValidationField $field Contains field and validation information.
     * @return mixed Returns the valid value or `Invalid`.
     * @throws \InvalidArgumentException Throws an exception when `$type` is not recognized.
     * @throws RefNotFoundException Throws an exception when internal validation has a reference that isn't found.
     */
    protected function validateSingleType($value, string $type, ValidationField $field) {
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
                trigger_error('The timestamp type is deprecated. Use an integer with a format of timestamp instead.', E_USER_DEPRECATED);
                $result = $this->validateTimestamp($value, $field);
                break;
            case 'datetime':
                trigger_error('The datetime type is deprecated. Use a string with a format of date-time instead.', E_USER_DEPRECATED);
                $result = $this->validateDatetime($value, $field);
                break;
            case 'array':
                $result = $this->validateArray($value, $field);
                break;
            case 'object':
                $result = $this->validateObject($value, $field);
                break;
            case 'null':
                $result = $this->validateNull($value, $field);
                break;
            case '':
                // No type was specified so we are valid.
                $result = $value;
                break;
            default:
                throw new \InvalidArgumentException("Unrecognized type $type.", 500);
        }
        return $result;
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
            $field->addTypeError($value, 'boolean');
            return Invalid::value();
        }

        return $value;
    }

    /**
     * Validate and integer.
     *
     * @param mixed $value The value to validate.
     * @param ValidationField $field The validation results to add.
     * @return int|Invalid Returns the cleaned value or **null** if validation fails.
     */
    protected function validateInteger($value, ValidationField $field) {
        if ($field->val('format') === 'timestamp') {
            return $this->validateTimestamp($value, $field);
        }

        $result = filter_var($value, FILTER_VALIDATE_INT);

        if ($result === false) {
            $field->addTypeError($value, 'integer');
            return Invalid::value();
        }

        $result = $this->validateNumberProperties($result, $field);

        return $result;
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
            $field->addTypeError($value, 'timestamp');
            $result = Invalid::value();
        }
        return $result;
    }

    /**
     * Validate specific numeric validation properties.
     *
     * @param int|float $value The value to test.
     * @param ValidationField $field Field information.
     * @return int|float|Invalid Returns the number of invalid.
     */
    private function validateNumberProperties($value, ValidationField $field) {
        $count = $field->getErrorCount();

        if ($multipleOf = $field->val('multipleOf')) {
            $divided = $value / $multipleOf;

            if ($divided != round($divided)) {
                $field->addError('multipleOf', ['messageCode' => 'The value must be a multiple of {multipleOf}.', 'multipleOf' => $multipleOf]);
            }
        }

        if ($maximum = $field->val('maximum')) {
            $exclusive = $field->val('exclusiveMaximum');

            if ($value > $maximum || ($exclusive && $value == $maximum)) {
                if ($exclusive) {
                    $field->addError('maximum', ['messageCode' => 'The value must be less than {maximum}.', 'maximum' => $maximum]);
                } else {
                    $field->addError('maximum', ['messageCode' => 'The value must be less than or equal to {maximum}.', 'maximum' => $maximum]);
                }
            }
        }

        if ($minimum = $field->val('minimum')) {
            $exclusive = $field->val('exclusiveMinimum');

            if ($value < $minimum || ($exclusive && $value == $minimum)) {
                if ($exclusive) {
                    $field->addError('minimum', ['messageCode' => 'The value must be greater than {minimum}.', 'minimum' => $minimum]);
                } else {
                    $field->addError('minimum', ['messageCode' => 'The value must be greater than or equal to {minimum}.', 'minimum' => $minimum]);
                }
            }
        }

        return $field->getErrorCount() === $count ? $value : Invalid::value();
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
            $field->addTypeError($value, 'number');
            return Invalid::value();
        }

        $result = $this->validateNumberProperties($result, $field);

        return $result;
    }

    /**
     * Validate a string.
     *
     * @param mixed $value The value to validate.
     * @param ValidationField $field The validation results to add.
     * @return string|Invalid Returns the valid string or **null** if validation fails.
     */
    protected function validateString($value, ValidationField $field) {
        if ($field->val('format') === 'date-time') {
            $result = $this->validateDatetime($value, $field);

            return $result;
        }

        if (is_string($value) || is_numeric($value)) {
            $value = $result = (string)$value;
        } else {
            $field->addTypeError($value, 'string');

            return Invalid::value();
        }

        $mbStrLen = mb_strlen($value);
        if (($minLength = $field->val('minLength', 0)) > 0 && $mbStrLen < $minLength) {
            $field->addError(
                'minLength',
                [
                    'messageCode' => 'The value should be at least {minLength} {minLength,plural,character,characters} long.',
                    'minLength' => $minLength,
                ]
            );
        }

        if (($maxLength = $field->val('maxLength', 0)) > 0 && $mbStrLen > $maxLength) {
            $field->addError(
                'maxLength',
                [
                    'messageCode' => 'The value is {overflow} {overflow,plural,character,characters} too long.',
                    'maxLength' => $maxLength,
                    'overflow' => $mbStrLen - $maxLength,
                ]
            );
        }

        $useLengthAsByteLength = !$this->hasFlag(self::VALIDATE_STRING_LENGTH_AS_UNICODE);
        $maxByteLength = $field->val('maxByteLength') ?? ($useLengthAsByteLength ? $maxLength : null);
        if ($maxByteLength !== null && $maxByteLength > 0) {
            $byteStrLen = strlen($value);
            if ($byteStrLen > $maxByteLength) {
                $field->addError(
                    'maxByteLength',
                    [
                        'messageCode' => 'The value is {overflow} {overflow,plural,byte,bytes} too long.',
                        'maxLength' => $maxLength,
                        'overflow' => $byteStrLen - $maxByteLength,
                    ]
                );
            }
        }

        if ($pattern = $field->val('pattern')) {
            $regex = '`'.str_replace('`', preg_quote('`', '`'), $pattern).'`';

            if (!preg_match($regex, $value)) {
                $field->addError(
                    'pattern',
                    [
                        'messageCode' => $field->val('x-patternMessageCode', 'The value doesn\'t match the required pattern {pattern}.'),
                        'pattern' => $regex,
                    ]
                );
            }
        }
        if ($format = $field->val('format')) {
            $type = $format;
            switch ($format) {
                case 'date':
                    $result = $this->validateDatetime($result, $field);
                    if ($result instanceof \DateTimeInterface) {
                        $result = $result->format("Y-m-d\T00:00:00P");
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
                    $type = 'URL';
                    $result = filter_var($result, FILTER_VALIDATE_URL);
                    break;
                default:
                    trigger_error("Unrecognized format '$format'.", E_USER_NOTICE);
            }
            if ($result === false) {
                $field->addError('format', [
                    'format' => $format,
                    'formatCode' => $type,
                    'value' => $value,
                    'messageCode' => '{value} is not a valid {formatCode}.'
                ]);
            }
        }

        if ($field->isValid()) {
            return $result;
        } else {
            return Invalid::value();
        }
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
            } catch (\Throwable $ex) {
                $value = Invalid::value();
            }
        } elseif (is_int($value) && $value > 0) {
            try {
                $value = new \DateTimeImmutable('@'.(string)round($value));
            } catch (\Throwable $ex) {
                $value = Invalid::value();
            }
        } else {
            $value = Invalid::value();
        }

        if (Invalid::isInvalid($value)) {
            $field->addTypeError($value, 'date/time');
        }
        return $value;
    }

    /**
     * Recursively resolve allOf inheritance tree and return a merged resource specification
     *
     * @param ValidationField $field The validation results to add.
     * @return array Returns an array of merged specs.
     * @throws ParseException Throws an exception if an invalid allof member is provided
     * @throws RefNotFoundException Throws an exception if the array has an items `$ref` that cannot be found.
     */
    private function resolveAllOfTree(ValidationField $field) {
        $result = [];

        foreach($field->getAllOf() as $allof) {
            if (!is_array($allof) || empty($allof)) {
                throw new ParseException("Invalid allof member in {$field->getSchemaPath()}, array expected", 500);
            }

            list ($items, $schemaPath) = $this->lookupSchema($allof, $field->getSchemaPath());

            $allOfValidation = new ValidationField(
                $field->getValidation(),
                $items,
                '',
                $schemaPath,
                $field->getOptions()
            );

            if($allOfValidation->hasAllOf()) {
                $result = array_replace_recursive($result, $this->resolveAllOfTree($allOfValidation));
            } else {
                $result = array_replace_recursive($result, $items);
            }
        }

        return $result;
    }

    /**
     * Validate allof tree
     *
     * @param mixed $value The value to validate.
     * @param ValidationField $field The validation results to add.
     * @return array|Invalid Returns an array or invalid if validation fails.
     * @throws RefNotFoundException Throws an exception if the array has an items `$ref` that cannot be found.
     */
    private function validateAllOf($value, ValidationField $field) {
        $allOfValidation = new ValidationField(
            $field->getValidation(),
            $this->resolveAllOfTree($field),
            '',
            $field->getSchemaPath(),
            $field->getOptions()
        );

        return $this->validateField($value, $allOfValidation);
    }

    /**
     * Validate an array.
     *
     * @param mixed $value The value to validate.
     * @param ValidationField $field The validation results to add.
     * @return array|Invalid Returns an array or invalid if validation fails.
     * @throws RefNotFoundException Throws an exception if the array has an items `$ref` that cannot be found.
     */
    protected function validateArray($value, ValidationField $field) {
        if ((!is_array($value) || (count($value) > 0 && !array_key_exists(0, $value))) && !$value instanceof \Traversable) {
            $field->addTypeError($value, 'array');
            return Invalid::value();
        } else {
            if ((null !== $minItems = $field->val('minItems')) && count($value) < $minItems) {
                $field->addError(
                    'minItems',
                    [
                        'messageCode' => 'This must contain at least {minItems} {minItems,plural,item,items}.',
                        'minItems' => $minItems,
                    ]
                );
            }
            if ((null !== $maxItems = $field->val('maxItems')) && count($value) > $maxItems) {
                $field->addError(
                    'maxItems',
                    [
                        'messageCode' => 'This must contain no more than {maxItems} {maxItems,plural,item,items}.',
                        'maxItems' => $maxItems,
                    ]
                );
            }

            if ($field->val('uniqueItems') && count($value) > count(array_unique($value))) {
                $field->addError(
                    'uniqueItems',
                    [
                        'messageCode' => 'The array must contain unique items.',
                    ]
                );
            }

            if ($field->val('items') !== null) {
                list ($items, $schemaPath) = $this->lookupSchema($field->val('items'), $field->getSchemaPath().'/items');

                // Validate each of the types.
                $itemValidation = new ValidationField(
                    $field->getValidation(),
                    $items,
                    '',
                    $schemaPath,
                    $field->getOptions()
                );

                $result = [];
                $count = 0;
                foreach ($value as $i => $item) {
                    $itemValidation->setName($field->getName()."/$i");
                    $validItem = $this->validateField($item, $itemValidation);
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
     * Validate an object.
     *
     * @param mixed $value The value to validate.
     * @param ValidationField $field The validation results to add.
     * @return object|Invalid Returns a clean object or **null** if validation fails.
     * @throws RefNotFoundException Throws an exception when a schema `$ref` is not found.
     */
    protected function validateObject($value, ValidationField $field) {
        if (!$this->isArray($value) || isset($value[0])) {
            $field->addTypeError($value, 'object');
            return Invalid::value();
        } elseif (is_array($field->val('properties')) || null !== $field->val('additionalProperties')) {
            // Validate the data against the internal schema.
            $value = $this->validateProperties($value, $field);
        } elseif (!is_array($value)) {
            $value = $this->toObjectArray($value);
        }

        if (($maxProperties = $field->val('maxProperties')) && count($value) > $maxProperties) {
            $field->addError(
                'maxProperties',
                [
                    'messageCode' => 'This must contain no more than {maxProperties} {maxProperties,plural,item,items}.',
                    'maxItems' => $maxProperties,
                ]
            );
        }

        if (($minProperties = $field->val('minProperties')) && count($value) < $minProperties) {
            $field->addError(
                'minProperties',
                [
                    'messageCode' => 'This must contain at least {minProperties} {minProperties,plural,item,items}.',
                    'minItems' => $minProperties,
                ]
            );
        }

        return $value;
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
     * Validate data against the schema and return the result.
     *
     * @param array|\Traversable|\ArrayAccess $data The data to validate.
     * @param ValidationField $field This argument will be filled with the validation result.
     * @return array|\ArrayObject|Invalid Returns a clean array with only the appropriate properties and the data coerced to proper types.
     * or invalid if there are no valid properties.
     * @throws RefNotFoundException Throws an exception of a property or additional property has a `$ref` that cannot be found.
     */
    protected function validateProperties($data, ValidationField $field) {
        $properties = $field->val('properties', []);
        $additionalProperties = $field->val('additionalProperties');
        $required = array_flip($field->val('required', []));
        $isRequest = $field->isRequest();
        $isResponse = $field->isResponse();

        if (is_array($data)) {
            $keys = array_keys($data);
            $clean = [];
        } else {
            $keys = array_keys(iterator_to_array($data));
            $class = get_class($data);
            $clean = new $class;

            if ($clean instanceof \ArrayObject && $data instanceof \ArrayObject) {
                $clean->setFlags($data->getFlags());
                $clean->setIteratorClass($data->getIteratorClass());
            }
        }
        $keys = array_combine(array_map('strtolower', $keys), $keys);

        $propertyField = new ValidationField($field->getValidation(), [], '', '', $field->getOptions());

        // Loop through the schema fields and validate each one.
        foreach ($properties as $propertyName => $property) {
            list($property, $schemaPath) = $this->lookupSchema($property, $field->getSchemaPath().'/properties/'.self::escapeRef($propertyName));

            $propertyField
                ->setField($property)
                ->setName(ltrim($field->getName().'/'.self::escapeRef($propertyName), '/'))
                ->setSchemaPath($schemaPath);

            $lName = strtolower($propertyName);
            $isRequired = isset($required[$propertyName]);

            // Check to strip this field if it is readOnly or writeOnly.
            if (($isRequest && $propertyField->val('readOnly')) || ($isResponse && $propertyField->val('writeOnly'))) {
                unset($keys[$lName]);
                continue;
            }

            // Check for required fields.
            if (!array_key_exists($lName, $keys)) {
                if ($field->isSparse()) {
                    // Sparse validation can leave required fields out.
                } elseif ($propertyField->hasVal('default')) {
                    $clean[$propertyName] = $propertyField->val('default');
                } elseif ($isRequired) {
                    $propertyField->addError(
                        'required',
                        ['messageCode' => '{property} is required.', 'property' => $propertyName]
                    );
                }
            } else {
                $value = $data[$keys[$lName]];

                if (in_array($value, [null, ''], true) && !$isRequired && !($propertyField->val('nullable') || $propertyField->hasType('null'))) {
                    if ($propertyField->getType() !== 'string' || $value === null) {
                        continue;
                    }
                }

                $clean[$propertyName] = $this->validateField($value, $propertyField);
            }

            unset($keys[$lName]);
        }

        // Look for extraneous properties.
        if (!empty($keys)) {
            if ($additionalProperties) {
                list($additionalProperties, $schemaPath) = $this->lookupSchema(
                    $additionalProperties,
                    $field->getSchemaPath().'/additionalProperties'
                );

                $propertyField = new ValidationField(
                    $field->getValidation(),
                    $additionalProperties,
                    '',
                    $schemaPath,
                    $field->getOptions()
                );

                foreach ($keys as $key) {
                    $propertyField
                        ->setName(ltrim($field->getName()."/$key", '/'));

                    $valid = $this->validateField($data[$key], $propertyField);
                    if (Invalid::isValid($valid)) {
                        $clean[$key] = $valid;
                    }
                }
            } elseif ($this->hasFlag(Schema::VALIDATE_EXTRA_PROPERTY_NOTICE)) {
                $msg = sprintf("Unexpected properties: %s.", implode(', ', $keys));
                trigger_error($msg, E_USER_NOTICE);
            } elseif ($this->hasFlag(Schema::VALIDATE_EXTRA_PROPERTY_EXCEPTION)) {
                $field->addError('unexpectedProperties', [
                    'messageCode' => 'Unexpected {extra,plural,property,properties}: {extra}.',
                    'extra' => array_values($keys),
                ]);
            }
        }

        return $clean;
    }

    /**
     * Escape a JSON reference field.
     *
     * @param string $field The reference field to escape.
     * @return string Returns an escaped reference.
     */
    public static function escapeRef(string $field): string {
        return str_replace(['~', '/'], ['~0', '~1'], $field);
    }

    /**
     * Whether or not the schema has a flag (or combination of flags).
     *
     * @param int $flag One or more of the **Schema::VALIDATE_*** constants.
     * @return bool Returns **true** if all of the flags are set or **false** otherwise.
     */
    public function hasFlag(int $flag): bool {
        return ($this->flags & $flag) === $flag;
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
        $field->addError('type', ['messageCode' => 'The value should be null.', 'type' => 'null']);
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
                'enum',
                [
                    'messageCode' => 'The value must be one of: {enum}.',
                    'enum' => $enum,
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
    private function callValidators($value, ValidationField $field) {
        $valid = true;

        // Strip array references in the name except for the last one.
        $key = $field->getSchemaPath();
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
            $field->addError('invalid', ['messageCode' => 'The value is invalid.']);
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
    public function jsonSerialize(): mixed {
        $seen = [$this];
        return $this->jsonSerializeInternal($seen);
    }

    /**
     * Return the JSON data for serialization with massaging for Open API.
     *
     * - Swap data/time & timestamp types for Open API types.
     * - Turn recursive schema pointers into references.
     *
     * @param Schema[] $seen Schemas that have been seen during traversal.
     * @return array Returns an array of data that `json_encode()` will recognize.
     */
    private function jsonSerializeInternal(array $seen): array {
        $fix = function ($schema) use (&$fix, $seen) {
            if ($schema instanceof Schema) {
                if (in_array($schema, $seen, true)) {
                    return ['$ref' => '#/components/schemas/'.($schema->getID() ?: '$no-id')];
                } else {
                    $seen[] = $schema;
                    return $schema->jsonSerializeInternal($seen);
                }
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
     * Get the class that's used to contain validation information.
     *
     * @return Validation|string Returns the validation class.
     * @deprecated
     */
    public function getValidationClass() {
        trigger_error('Schema::getValidationClass() is deprecated. Use Schema::getValidationFactory() instead.', E_USER_DEPRECATED);
        return $this->validationClass;
    }

    /**
     * Set the class that's used to contain validation information.
     *
     * @param Validation|string $class Either the name of a class or a class that will be cloned.
     * @return $this
     * @deprecated
     */
    public function setValidationClass($class) {
        trigger_error('Schema::setValidationClass() is deprecated. Use Schema::setValidationFactory() instead.', E_USER_DEPRECATED);

        if (!is_a($class, Validation::class, true)) {
            throw new \InvalidArgumentException("$class must be a subclass of ".Validation::class, 500);
        }

        $this->setValidationFactory(function () use ($class) {
            if ($class instanceof Validation) {
                $result = clone $class;
            } else {
                $result = new $class;
            }
            return $result;
        });
        $this->validationClass = $class;
        return $this;
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
     * Get the ID for the schema.
     *
     * @return string
     */
    public function getID(): string {
        return $this->schema['id'] ?? '';
    }

    /**
     * Set the ID for the schema.
     *
     * @param string $id The new ID.
     * @return $this
     */
    public function setID(string $id) {
        $this->schema['id'] = $id;

        return $this;
    }

    /**
     * Whether a offset exists.
     *
     * @param mixed $offset An offset to check for.
     * @return boolean true on success or false on failure.
     * @link http://php.net/manual/en/arrayaccess.offsetexists.php
     */
    public function offsetExists(mixed $offset): bool {
        return isset($this->schema[$offset]);
    }

    /**
     * Offset to retrieve.
     *
     * @param mixed $offset The offset to retrieve.
     * @return mixed Can return all value types.
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     */
    public function offsetGet(mixed $offset): mixed {
        return isset($this->schema[$offset]) ? $this->schema[$offset] : null;
    }

    /**
     * Offset to set.
     *
     * @param mixed $offset The offset to assign the value to.
     * @param mixed $value The value to set.
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     */
    public function offsetSet(mixed $offset, mixed $value): void {
        $this->schema[$offset] = $value;
    }

    /**
     * Offset to unset.
     *
     * @param mixed $offset The offset to unset.
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     */
    public function offsetUnset($offset): void {
        unset($this->schema[$offset]);
    }

    /**
     * Resolve the schema attached to a discriminator.
     *
     * @param mixed $value The value to search for the discriminator.
     * @param ValidationField $field The current node's schema information.
     * @return ValidationField|null Returns the resolved schema or **null** if it can't be resolved.
     * @throws ParseException Throws an exception if the discriminator isn't a string.
     */
    private function resolveDiscriminator($value, ValidationField $field, array $visited = []) {
        $propertyName = $field->val('discriminator')['propertyName'] ?? '';
        if (empty($propertyName) || !is_string($propertyName)) {
            throw new ParseException("Invalid propertyName for discriminator at {$field->getSchemaPath()}", 500);
        }

        $propertyFieldName = ltrim($field->getName().'/'.self::escapeRef($propertyName), '/');

        // Do some basic validation checking to see if we can even look at the property.
        if (!$this->isArray($value)) {
            $field->addTypeError($value, 'object');
            return null;
        } elseif (empty($value[$propertyName])) {
            $field->getValidation()->addError(
                $propertyFieldName,
                'required',
                ['messageCode' => '{property} is required.', 'property' => $propertyName]
            );
            return null;
        }

        $propertyValue = $value[$propertyName];
        if (!is_string($propertyValue)) {
            $field->getValidation()->addError(
                $propertyFieldName,
                'type',
                [
                    'type' => 'string',
                    'value' => is_scalar($value) ? $value : null,
                    'messageCode' => is_scalar($value) ? "{value} is not a valid string." : "The value is not a valid string."
                ]
            );
            return null;
        }

        $mapping = $field->val('discriminator')['mapping'] ?? '';
        if (isset($mapping[$propertyValue])) {
            $ref = $mapping[$propertyValue];

            if (strpos($ref, '#') === false) {
                $ref = '#/components/schemas/'.self::escapeRef($ref);
            }
        } else {
            // Don't let a property value provide its own ref as that may pose a security concern..
            $ref = '#/components/schemas/'.self::escapeRef($propertyValue);
        }

        // Validate the reference against the oneOf constraint.
        $oneOf = $field->val('oneOf', []);
        if (!empty($oneOf) && !in_array(['$ref' => $ref], $oneOf)) {
            $field->getValidation()->addError(
                $propertyFieldName,
                'oneOf',
                [
                    'type' => 'string',
                    'value' => is_scalar($propertyValue) ? $propertyValue : null,
                    'messageCode' => is_scalar($propertyValue) ? "{value} is not a valid option." : "The value is not a valid option."
                ]
            );
            return null;
        }

        try {
            // Lookup the schema.
            $visited[$field->getSchemaPath()] = true;

            list($schema, $schemaPath) = $this->lookupSchema(['$ref' => $ref], $field->getSchemaPath());
            if (isset($visited[$schemaPath])) {
                throw new RefNotFoundException('Cyclical ref.', 508);
            }

            $result = new ValidationField(
                $field->getValidation(),
                $schema,
                $field->getName(),
                $schemaPath,
                $field->getOptions()
            );
            if (!empty($schema['discriminator'])) {
                return $this->resolveDiscriminator($value, $result, $visited);
            } else {
                return $result;
            }
        } catch (RefNotFoundException $ex) {
            // Since this is a ref provided by the value it is technically a validation error.
            $field->getValidation()->addError(
                $propertyFieldName,
                'propertyName',
                [
                    'type' => 'string',
                    'value' => is_scalar($propertyValue) ? $propertyValue : null,
                    'messageCode' => is_scalar($propertyValue) ? "{value} is not a valid option." : "The value is not a valid option."
                ]
            );
            return null;
        }
    }
}
