# Garden Schema

[![Build Status](https://img.shields.io/travis/vanilla/garden-schema.svg?style=flat)](https://travis-ci.org/vanilla/garden-schema)
[![Coverage](https://img.shields.io/scrutinizer/coverage/g/vanilla/garden-schema.svg?style=flat)](https://scrutinizer-ci.com/g/vanilla/garden-schema/)
[![Packagist Version](https://img.shields.io/packagist/v/vanilla/garden-schema.svg?style=flat)](https://packagist.org/packages/vanilla/garden-schema)
![MIT License](https://img.shields.io/packagist/l/vanilla/garden-schema.svg?style=flat)
[![CLA](https://cla-assistant.io/readme/badge/vanilla/garden-schema)](https://cla-assistant.io/vanilla/garden-schema)

The Garden Schema is a simple data validation and cleaning library based on [OpenAPI 3.0 Schema](https://github.com/OAI/OpenAPI-Specification/blob/master/versions/3.0.0.md#schemaObject).

## Features

- Define the data structures of PHP arrays of any depth, and validate them.

- Validated data is cleaned and coerced into appropriate types.

- The schema defines a whitelist of allowed data and strips out all extraneous data.

- The **Schema** class understands a subset of data in [OpenAPI Schema](https://github.com/OAI/OpenAPI-Specification/blob/master/versions/3.0.0.md#schemaObject) format. We will add more support for the built-in JSON schema validation as time goes on.

- Developers can use a shorter schema format in order to define schemas in code rapidly. We built this class to be as easy to use as possible. Avoid developer groans as they lock down their data.

- Add custom validator callbacks to support practically any validation scenario.

- Override the validation class in order to customize the way errors are displayed for your own application.

## Uses

Garden Schema is meant to be a generic wrapper for data validation. It should be valuable when you want to bullet-proof your code against user-submitted data. Here are some example uses:

- Check the data being submitted to your API endpoints. Define the schema at the beginning of your endpoint and validate the data before doing anything else. In this way you can be sure that you are using clean data and avoid a bunch of spaghetti checks later in your code. This was the original reason why we developed the Garden Schema.

- Clean user input. The Schema object will cast data to appropriate types and gracefully handle common use-cases (ex. converting the string "true" to true for booleans). This allows you to use more "===" checks in your code which helps avoid bugs in the longer term.

- Validate data before passing it to the database in order to present human-readable errors rather than cryptic database generated errors.

- Clean output before returning it. A lot of database drivers return data as strings even though it's defined as different types. The Schema will clean the data appropriately which is especially important for consumption by the non-PHP world.

## Basic Usage

To validate data you first create an instance of the **Schema** class and then call its **validate()** method.

```php
namespace Garden\Schema;

$schema = Schema::parse([...]);
try {
    $valid = $schema->validate($data);
} catch (ValidationException $ex) {
    ...
}
```

In the above example a **Schema** object is created with the schema definition passed to its constructor (more on that later). Data to be validated can then be passed to the **validate()** method. If the data is okay then a clean version is returned, otherwise a **ValidationException** is thrown.

## Defining Schemas

The **Schema** class is instantiated with an array defining the schema. The array can be in [OpenAPI 3.0 Schema](https://github.com/OAI/OpenAPI-Specification/blob/master/versions/3.0.0.md#schemaObject) format or it can be in custom short format. It is recommended you define your schemas in the OpenAPI format, but the short format is good for those wanting to write quick prototypes. The short format will be described in this section.

By default the schema is an array where each element of the array defines an object property. By "object" we mean javascript object or PHP array with string keys. There are several ways a property can be defined:
 
```php
[
    '<property>', // basic property, can be any type
    '<property>?', // optional property
    '<property>:<type>?', // optional property with specific type

    '<property>:<type>?' => 'Description', // optional, typed property with description
    '<property>?' => ['type' => '<type'>, 'description' => '...'], // longer format
    
    '<property>:o' => [ // object property with nested schema
        '<property>:<type>' => '...',
        ...
    ],
    '<property>:a' => '<type>', // array property with element type
    '<property>:a' => [ // array property with object element type
        '<property>:<type>' => '...',
        ...
    ]
]
 ```

You can quickly define an object schema by giving just as much information as you need. You can create a schema that is nested as deeply as you want in order to validate very complex data. This short schema is converted into a JSON schema compatible array internally and you can see this array with the **jsonSerialize()** method.

We provide first-class support for descriptions because we believe in writing readable code right off the bat. If you don't like this you can just leave the descriptions out and they will be left empty in the schema.
 
### Types and Short Types

The **Schema** class supports the following types. Each type has a short-form and a long-form. Usually you use the short-form when defining a schema in code and it gets converted to the long-form internally, including when used in errors.

Type      | Short-form
--------- | ----------
boolean   | b, bool
string    | s, str
integer   | i, int
number    | f, float
timestamp | ts
datetime  | dt
array     | a
object    | o

### Arrays and Objects

The array and object types are a bit special as they contain several elements rather than a single value. Because of this you can define the type of data that should be in those properties. Here are some examples:

```php
$schema = Schema::parse([
    'items:a', // array of any type
    'tags:a' => 's', // array of strings
    
    'attributes:o', // object of any type
    'user:o' => [ // an object with specific properties
        'name:s',
        'email:s?'
    ]
]);
```

### Non-Object Schemas

By default, schemas define an object because that is the most common use for a schema. If you want a schema to represent an array or even a basic type you define a single field with no name. The following example defines an array of objects (i.e. the output of a database query).

```php
$schema = Schema::parse([
    ':a' => [
        'id:i',
        'name:s',
        'birthday:dt'
    ]
]);
```

This schema would apply to something like the following data:
 
```php
[
    ['id' => 1, 'name' => 'George', 'birthday' => '1732-02-22'],
    ['id' => 16, 'name' => 'Abraham', 'birthday' => '1809-02-12'],
    ['id' => 32, 'name' => 'Franklin', 'birthday' => '1882-01-30']
]
```

### Optional Properties and Nullable Properties

When defining an object schema you can use a "?" to say that the property is optional. This means that the property can be completely omitted during validation. This is not the same a providing a **null** value for the property which is considered invalid for optional properties.

If you want a property to allow null values you can specify the `nullable` attribute on the property. There are two ways to do this:

```php
[
    // You can specify allowNull as a property attribute.
    'opt1:s?' => ['nullable' => true],
    
    // You can specify null as an optional type in the declaration.
    'opt2:s|n?' => 'Another nullable, optional property.'
] 
```

### Default Values

You can specify a default value on object properties. If the property is omitted during validation then the default value will be used. Note that default values are not applied during sparse validation.

## Validating Data

Once you have a schema you validate data using the **validate()** or **isValid()** methods.

### The Schema::validate() method

You pass the data you want to validate to **Schema::validate()** and it it either returns a cleaned copy of your data or throws a **ValidationException**.

```php
$schema = Schema::parse(['id:i', 'name:s']);
try {
    // $u1 will be ['id' => 123, 'name' => 'John']
    $u1 = $schema->validate(['id' => '123', 'name' => 'John']);
    
    // This will thow an exception.
    $u2 = $schema->validate(['id' => 'foo']);
} catch (ValidationException $ex) {
    // $ex->getMessage() will be: 'id is not a valid integer. name is required.'  
}
```

Calling **validate()** on user-submitted data allows you to check your data early and bail out if it isn't correct. If you just want to check your data without throwing an exception the **isValid()** method is a convenience method that returns true or false depending on whether or not the data is valid.

```php
$schema = Schema::parse(['page:i', 'count:i?']);

if ($schema->isValid(['page' => 5]) {
    // This will be hit.
}

if ($schema->isValid(['page' => 2, 'count' => 'many']) {
    // This will not be hit because the data isn't valid.
}
```

### The ValidationException and Validation Classes

When you call **validate()** and validation fails a **ValidationException** is thrown. This exception contains a property that is a **Validation** object which contains more information about the fields that have failed.

If you are writing an API, you can **json_encode()** the **ValidationException** and it should provide a rich set of data that will help any consumer figure out exactly what they did wrong. You can also use various properties of the **Validation** property to help render the error output appropriately. 

#### The Validation JSON Format

The `Validation` object and `ValidationException` both encode to a [specific format]('open-api.json'). Here is an example:

```js
ValidationError = {
    "message": "string", // Main error message.
    "code": "integer", // HTTP-style status code.
    "errors": { // Specific field errors.
        "<fieldRef>": [ // Each key is a JSON reference field name.
            {
                "message": "string", // Field error message.
                "error": "string", // Specific error code, usually a schema attribute.
                "code": "integer" // Optional field error code.
            }
        ]
    }
}
```

This format is optimized for helping present errors to user interfaces. You can loop through the specific `errors` collection and line up errors with their inputs on a user interface. For deeply nested objects, the field name is a JSON reference.

## Schema References

OpenAPI allows for schemas to be accessed with references using the `$ref` attribute. Using references allows you to define commonly used schemas in one place and then reference them from many locations.

To use references you must:

1. Define the schema you want to reference somewhere.
2. Reference the schema with a `$ref` attribute.
3. Add a schema lookup function to your main schema with `Schema::setRefLookp()`

### Defining a Reusable Schema

The OpenAPI specification places all reusable schemas under `/components/schemas`. If you are defining everything in a big array that is a good place to put them.

```php
$components = [
    'components' => [
        'schemas' => [
            'User' => [
                'type' => 'object',
                'properties' => [
                    'id' => [
                        'type' => 'integer'
                    ],
                    'username' => [
                        'type' => 'string'
                    ]
                ]
            ]
        ]
    ]
]
```

### Referencing Schemas With `$ref`

Reference the schema's path with keys separated by `/` characters.

```php
$userArray = [
    'type' => 'array',
    'items' => [
        '$ref' => '#/components/schemas/User'
    ]
]
```

### Using `Schema::setRefLookup()` to Resolve References

The `Schema` class has a `setRefLookup()` method that lets you add a callable that is use to resolve references. The callable should have the following signature:

```php
function(string $ref): array|Schema|null {
   ...
}
```

The function takes the string from the `$ref` attribute and returns a schema array, `Schema` object, or **null** if the schema cannot be found. Garden Schema has a default implementation of a ref lookup in the `ArrayRefLookup` class that can resolve references from a static array. This should be good enough for most uses, but you are always free to define your own.

You can put everything together like this:

```php
$sch = new Schema($userArray);
$sch->setRefLookup(new ArrayRefLookup($components));

$valid = $sch->validate(...);
```

The references are resolved during validation so if there are any mistakes in your references then a `RefNotFoundException` is thrown during validation, not when you set your schema or ref lookup function.

## Validation Options

Both **validate()** and **isValid()** can take an additional **$options** argument which modifies the behavior of the validation slightly, depending on the option.

### The `request` Option

You can pass an option of `['request' => true]` to specify that you are validating request data. When validating request data, properties that have been marked as `readOnly: true` will be treated as if they don't exist, even if they are marked as required.

### The `response` Option

You can pass an option of `['response' => true]` to specify that you are validating response data. When validating response data, properties that have been marked as `writeOnly: true` will be treated as if they don't exist, even if they are marked as required.

### The `sparse` Option

You can pass an option of `['sparse' => true]` to specify a sparse validation. When you do a sparse validation, missing properties do not give errors and the sparse data is returned. Sparse validation allows you to use the same schema for inserting vs. updating records. This is common in databases or APIs with POST vs. PATCH requests.

## Overriding the Validation Class and Localization

Since schemas generate error messages, localization may be an issue. Although the Garden Schema doesn't offer any localization capabilities itself, it is designed to be extended in order to add localization yourself. You do this by subclassing the **Validation** class and overriding its **translate()** method. Here is a basic example:

```php
class LocalizedValidation extends Validation {
    public function translate($str) {
        if (substr($str, 0, 1) === '@') {
            // This is a literal string that bypasses translation.
            return substr($str, 1);
        } else {
            return gettext($str);
        }
    }
}

// Install your class like so:
$schema = Schema::parse([...]);
$schema->setValidationClass(LocalizedValidation::class);
```

There are a few things to note in the above example:

- When overriding **translate()** be sure to handle the case where a string starts with the '@' character. Such strings should not be translated and have the character removed.

- You tell a **Schema** object to use your specific **Validation** subclass with the **setValidationClass()**. This method takes either a class name or an object instance. If you pass an object it will be cloned every time a validation object is needed. This is good when you want to use dependency injection and your class needs more sophisticated instantiation.

## JSON Schema Support

The **Schema** object is a wrapper for an [OpenAPI Schema](https://github.com/OAI/OpenAPI-Specification/blob/master/versions/3.0.0.md#schemaObject) array. This means that you can pass a valid JSON schema to Schema's constructor. The table below lists the JSON Schema properties that are supported.

| Property | Applies To | Notes |
| -------- | ---------- | ----------- |
| [multipleOf](http://json-schema.org/latest/json-schema-validation.html#rfc.section.6.2.1) | integer/number | A numeric instance is only valid if division by this keyword's value results in an integer. |
| [maximum](http://json-schema.org/latest/json-schema-validation.html#rfc.section.6.2.2) | integer/number |  If the instance is a number, then this keyword validates only if the instance is less than or exactly equal to "maximum". |
| [exclusiveMaximum](http://json-schema.org/latest/json-schema-validation.html#rfc.section.6.2.3) | integer/number |  If the instance is a number, then the instance is valid only if it has a value strictly less than (not equal to) "exclusiveMaximum". |
| [minimum](http://json-schema.org/latest/json-schema-validation.html#rfc.section.6.2.4) | integer/number |  If the instance is a number, then this keyword validates only if the instance is greater than or exactly equal to "minimum". |
| [exclusiveMinimum](http://json-schema.org/latest/json-schema-validation.html#rfc.section.6.2.5) | integer/number |  If the instance is a number, then the instance is valid only if it has a value strictly greater than (not equal to) "exclusiveMinimum". |
| [maxLength](http://json-schema.org/latest/json-schema-validation.html#rfc.section.6.6) | string | Limit the length of a string. |
| [minLength](http://json-schema.org/latest/json-schema-validation.html#rfc.section.6.7) | string | Minimum length of a string. |
| [pattern](http://json-schema.org/latest/json-schema-validation.html#rfc.section.6.8) | string | A regular expression without delimiters. You can add a custom error message with the `x-patternMessageCode` field. |
| [items](http://json-schema.org/latest/json-schema-validation.html#rfc.section.6.9) | array | Ony supports a single schema. |
| [maxItems](http://json-schema.org/latest/json-schema-validation.html#rfc.section.6.11) | array | Limit the number of items in an array. |
| [minItems](http://json-schema.org/latest/json-schema-validation.html#rfc.section.6.12) | array | Minimum number of items in an array. |
| [uniqueItems](http://json-schema.org/latest/json-schema-validation.html#rfc.section.6.4.5) | array | All items must be unique. |
| [maxProperties](http://json-schema.org/latest/json-schema-validation.html#rfc.section.6.5.1) | object | Limit the number of properties on an object. |
| [minProperties](http://json-schema.org/latest/json-schema-validation.html#rfc.section.6.5.2) | object | Minimum number of properties on an object.  |
| [additionalProperties](http://json-schema.org/latest/json-schema-validation.html#rfc.section.6.5.6) | object | Validate additional properties against a schema. Can also be **true** to always validate. |
| [required](http://json-schema.org/latest/json-schema-validation.html#rfc.section.6.17) | object | Names of required object properties. |
| [properties](http://json-schema.org/latest/json-schema-validation.html#rfc.section.6.18) | object | Specify schemas for object properties. |
| [enum](http://json-schema.org/latest/json-schema-validation.html#rfc.section.6.23) | any | Specify an array of valid values. |
| [type](http://json-schema.org/latest/json-schema-validation.html#rfc.section.6.25) | any | Specify a type of an array of types to validate a value. |
| [default](http://json-schema.org/latest/json-schema-validation.html#rfc.section.7.3) | object | Applies to a schema that is in an object property. |
| [format](http://json-schema.org/latest/json-schema-validation.html#rfc.section.8.3) | string | Support for date-time, email, ipv4, ipv6, ip, uri. | 

## OpenAPI Schema Support

OpenAPI defines some extended properties that are applied during validation.

| Property | Type | Notes |
| -------- | ---- | ----- |
| nullable | boolean | If a field is nullable then it can also take the value **null**. |
| readOnly | boolean | Relevant only for Schema "properties" definitions. Declares the property as "read only". This means that it MAY be sent as part of a response but SHOULD NOT be sent as part of the request. If the property is marked as readOnly being true and is in the required list, the required will take effect on the response only. |
| writeOnly | boolean |  Relevant only for Schema "properties" definitions. Declares the property as "write only". Therefore, it MAY be sent as part of a request but SHOULD NOT be sent as part of the response. If the property is marked as writeOnly being true and is in the required list, the required will take effect on the request only. |
