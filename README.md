# Garden Schema

[![Build Status](https://img.shields.io/travis/vanilla/garden-schema.svg?style=flat)](https://travis-ci.org/vanilla/garden-schema)
[![Coverage](https://img.shields.io/scrutinizer/coverage/g/vanilla/garden-schema.svg?style=flat)](https://scrutinizer-ci.com/g/vanilla/garden-schema/)
[![Packagist Version](https://img.shields.io/packagist/v/vanilla/garden-schema.svg?style=flat)](https://packagist.org/packages/vanilla/garden-schema)
![MIT License](https://img.shields.io/packagist/l/vanilla/garden-schema.svg?style=flat)
[![CLA](https://cla-assistant.io/readme/badge/vanilla/garden-schema)](https://cla-assistant.io/vanilla/garden-schema)

The Garden Schema is a simple data validation and cleaning library based on [JSON Schema](http://json-schema.org/).

## Features

- Define the data structure of any depth of PHP array and validate it.

- Validated data is cleaned and coerced into appropriate types.

- The schema defines a whitelist of allowed data and strips out all extraneous data. This adds

- The **Schema** class understands data in [JSON Schema](http://json-schema.org/) format. We will add more support for the built-in JSON schema validation as time goes on.

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

$schema = new Schema([...]);
try {
    $valid = $schema->validate($data);
} catch (ValidationException $ex) {
    ...
}
```

In the above example a **Schema** object is created with the schema definition passed to its constructor (more on that later). You then pass the data you want to validate to the **validate()** method. If the data is okay then a clean version is returned, otherwise a **ValidationException** is thrown.

## Defining Schemas

The **Schema** class is instantiated with an array defining the schema. The array can be in [JSON Schema](http://json-schema.org/) format or it can be in custom short format which is much quicker to develop with. The short format will be described in this section.

By default the schema is an array where each element of the array defines an object property. By "object" we mean javascript object or PHP array with string keys. There are several ways a property can be defined:
 
```php
[
    '<property>', // basic property can be any type
    '<property>?', // optional property
    '<property>:<type>?', // property with given type

    '<property>:<type>?' => 'Description', // property with description
    '<property>? => ['type' => '<type'>, 'description' => '...'], // longer format
    
    '<property>:o => [ // object property with nested schema
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

The **Schema** class supports the following types. Each type has a short-form and a long-form. Usually you use the short-form when defining a schema in code and it gets converted to the long-form.

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
$schema = new Schema([
    'items:a', // array of any type
    'tags:a' => 's', // array of strings
    
    'attributes:o', // object of any type
    'user:o' => [ // an object with specific properties
        'name:s',
        'email:s?'
    ]
]);
```

## Non-Object Schemas

By default, schemas define an object because that is the most common use for a schema. If you want a schema to represent an array or even a basic type you define a single field with no name. The following example defines an array of objects (i.e. the output of a database query).

```php
$schema = new Schema([
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

## Validating Data

Once you have a schema you validate data using the **validate()** or **isValid()** methods.

### The Schema::validate() method

You pass the data you want to validate to **Schema::validate()** and it it either returns a cleaned copy of your data or throws a **ValidationException**.

```php
$schema = new Schema(['id:i', 'name:s']);
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
$schema = new Schema(['page:i', 'count:i?']);

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

### Sparse Validation

Both **validate()** and **isValid()** can take an additional **$sparse** parameter which does a sparse validation if set to true.

When you do a sparse validation, missing properties do not give errors and the sparse data is returned. Sparse validation allows you to use the same schema for inserting vs. updating records. This is common in databases or APIs with POST vs. PATCH requests.

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
$schema = new Schema([...]);
$schema->setValidationClass(LocalizedValidation::class);
```

There are a few things to note in the above example:

- When overriding **translate()** be sure to handle the case where a string starts with the '@' character. Such strings should not be translated and have the character removed.

- You tell a **Schema** object to use your specific **Validation** subclass with the **setValidationClass()**. This method takes either a class name or an object instance. If you pass an object it will be cloned every time a validation object is needed. This is good when you want to use dependency injection and your class needs more sophisticated instantiation.