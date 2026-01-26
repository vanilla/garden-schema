# Garden Schema

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

- **Entity classes** with automatic schema generation from PHP properties, supporting nested entities, enums, and date-times.

- **Schema variants** for generating different schemas from a single Entity (Full, Fragment, Mutable, Create) to support common API patterns.

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

The **Schema** class supports the following types. Each type has one or more aliases. You can use an alias for brevity when defining a schema in code and it gets converted to the proper type internally, including when used in errors.

Type        | Aliases       | Notes |
----        | -------       | ----- |
boolean     | b, bool       |
string      | s, str, dt    | The "dt" alias adds a format of "date-time" and validates to `DateTimeInterface` instances |
integer     | i, int, ts    | The "ts" alias adds a format of "timestamp" and will convert date strings into integer timestamps on validation. |
number      | f, float      |
array       | a             |
object      | o             |

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

### Re-usable schemas

Schemas can be nested and composed.

```php
$userSchema = Schema::parse([
    'name:s',
    'email:s?'
]);

$recordSchema = Schema::parse([
    "uuid:s",
    "body:s",
    // User schema is required here.
    "user" => $userSchema,
]);

$recordSchema = Schema::parse([
    "uuid:s",
    "body:s",
    // User schema is optional here.
    "user?" => $userSchema,
]);

$recordSchema = Schema::parse([
    "uuid:s",
    "body:s",
    // Array of user schema objects.
    "users:a" => $userSchema,
]);
```

### Enum Values

You use a PHP `\BackedEnum` for validation.

```php
enum MyEnum: string {
    One: 'one',
    Two: 'two',
    Three: 'three',
}

// Shorthand
$schema = Schema::parse([
    "numberField" => MyEnum::class,
]);

// Long form
$schema = Schema::parse([
    "numberField" => [
        'type' => 'string',
        "enumClassName" => MyEnum::class,
    ],
]);

$value = $schema->validate(["numberField" => 'one']);
$value['numberField']; // MyEnum::One
```

### Entity Classes

Entity classes allow you to define strongly-typed data objects that automatically generate schemas from their properties using reflection. Validated data is cast into entity instances.

#### Defining an Entity

Create a class extending `Garden\Schema\Entity` with public properties:

```php
use Garden\Schema\Entity;

class User extends Entity {
    public string $name;
    public string $email;
    public int $age;
    public ?string $bio = null;  // Optional, nullable with default
}
```

#### Using Entities

Use `Entity::getSchema()` to get the generated schema, or `Entity::from()` to validate and create an instance:

```php
// Get the schema
$schema = User::getSchema();

// Validate and create an entity
$user = User::from([
    'name' => 'John',
    'email' => 'john@example.com',
    'age' => 30,
]);

$user->name; // 'John'
$user->age;  // 30 (integer)
```

If you pass an existing entity instance to `from()`, it returns that instance without re-validating:

```php
$user = User::from(['name' => 'John', 'email' => 'john@example.com', 'age' => 30]);
$same = User::from($user); // Returns $user, no validation
```

> **Note:** When an existing entity is passed, it is assumed to already be valid. If you've manually modified properties to invalid values, those won't be caught. Use `Entity::from($entity->toArray())` if you need to re-validate a modified entity.

#### Property Type Mapping

Entity properties are mapped to schema types as follows:

| PHP Type | Schema Type |
| -------- | ----------- |
| `string` | `string` |
| `int` | `integer` |
| `float` | `number` |
| `bool` | `boolean` |
| `array` | `array` |
| `ArrayObject` | `object` |
| `DateTimeImmutable` | `string` with `format: date-time` |
| `BackedEnum` subclass | `string` or `integer` with `enumClassName` |
| `Entity` subclass | Nested object schema with `entityClassName` |
| Untyped | No type validation (accepts any value) |

Properties with nullable types (`?string`) or default values are optional. All other typed properties are required.

#### The PropertySchema Attribute

Use `#[PropertySchema]` to customize property schemas. The provided schema array is merged with the auto-generated schema from the property's type, allowing you to add constraints while preserving type inference:

```php
use Garden\Schema\Entity;
use Garden\Schema\PropertySchema;

class Article extends Entity {
    public string $title;

    // Add constraints to auto-generated string type
    #[PropertySchema(['minLength' => 10, 'maxLength' => 5000])]
    public string $body;

    // Add items constraint to auto-generated array type
    #[PropertySchema(['items' => ['type' => 'string']])]
    public array $tags;

    // Multiple constraints
    #[PropertySchema(['items' => ['type' => 'string'], 'minItems' => 1])]
    public array $categories;
}
```

#### The PropertyAltNames Attribute

Use `#[PropertyAltNames]` to specify alternative property names that map to a property. This is useful for handling legacy field names, API versioning, or data from different sources:

```php
use Garden\Schema\Entity;
use Garden\Schema\PropertyAltNames;

class User extends Entity {
    #[PropertyAltNames('user_name', 'userName', 'uname')]
    public string $name;

    #[PropertyAltNames('e-mail', 'emailAddress')]
    public ?string $email = null;
}

// All of these work:
$user1 = User::from(['name' => 'John']);           // Main property name
$user2 = User::from(['user_name' => 'John']);      // First alt name
$user3 = User::from(['userName' => 'John']);       // Second alt name
$user4 = User::from(['uname' => 'John']);          // Third alt name

// Main property name takes precedence
$user5 = User::from(['name' => 'Main', 'user_name' => 'Alt']);
$user5->name; // 'Main'

// First matching alt name is used (in order defined)
$user6 = User::from(['userName' => 'Second', 'uname' => 'Third']);
$user6->name; // 'Second' (userName comes before uname in the attribute)
```

#### The ExcludeFromSchema Attribute

Use `#[ExcludeFromSchema]` to exclude a property from schema generation and validation. This is useful for computed properties, caches, or internal state that shouldn't be part of the data model:

```php
use Garden\Schema\Entity;
use Garden\Schema\ExcludeFromSchema;

class Article extends Entity {
    public string $title;
    public string $body;

    #[ExcludeFromSchema]
    public string $slug = '';  // Computed from title

    #[ExcludeFromSchema]
    public ?array $cache = null;  // Internal cache
}

$article = Article::from([
    'title' => 'Hello World',
    'body' => 'Content here',
    'slug' => 'ignored',  // This is ignored during validation
]);

$article->title; // 'Hello World'
$article->slug;  // '' (default value, input was ignored)

// Excluded properties can still be set directly
$article->slug = 'hello-world';
$article->cache = ['rendered' => '<p>Content here</p>'];

// Excluded properties are not included in toArray() or JSON output
$array = $article->toArray(); // ['title' => 'Hello World', 'body' => 'Content here']
```

#### Schema Variants

Entities support multiple schema variants for different API use cases. This allows a single Entity class to generate different schemas depending on the context:

| Variant | Use Case |
| ------- | -------- |
| `Full` | Complete entity with all properties (default). Used for single-item GET responses. |
| `Fragment` | Reduced version for lists. Omits large strings and detail fields. |
| `Mutable` | Fields that can be modified by consumers. Used for PATCH requests. |
| `Create` | Includes create-only fields. Used for POST requests. |

Use `Entity::getSchema()` with a `SchemaVariant` parameter to get different variants:

```php
use Garden\Schema\SchemaVariant;

// Get different schema variants
$fullSchema     = Article::getSchema();                      // Default: Full
$fullSchema     = Article::getSchema(SchemaVariant::Full);   // Explicit Full
$fragmentSchema = Article::getSchema(SchemaVariant::Fragment);
$mutableSchema  = Article::getSchema(SchemaVariant::Mutable);
$createSchema   = Article::getSchema(SchemaVariant::Create);
```

By default, all properties are included in all variants. Use attributes to customize which properties appear in each variant.

#### The ExcludeFromVariant Attribute

Use `#[ExcludeFromVariant]` to exclude a property from specific schema variants:

```php
use Garden\Schema\Entity;
use Garden\Schema\ExcludeFromVariant;
use Garden\Schema\SchemaVariant;

class Article extends Entity {
    public int $id;
    public string $title;

    // Exclude from Fragment (too large for list responses)
    #[ExcludeFromVariant(SchemaVariant::Fragment)]
    public string $body;

    // Exclude from Mutable (system-managed, not user-editable)
    #[ExcludeFromVariant(SchemaVariant::Mutable)]
    public \DateTimeImmutable $createdAt;

    #[ExcludeFromVariant(SchemaVariant::Mutable)]
    public \DateTimeImmutable $updatedAt;

    // Exclude from multiple variants
    #[ExcludeFromVariant(SchemaVariant::Fragment, SchemaVariant::Mutable)]
    public string $internalNotes;
}

// Fragment schema won't include body or internalNotes
$fragmentSchema = Article::getSchema(SchemaVariant::Fragment);

// Mutable schema won't include createdAt, updatedAt, or internalNotes
$mutableSchema = Article::getSchema(SchemaVariant::Mutable);
```

The attribute is repeatable, so you can also use multiple attributes:

```php
#[ExcludeFromVariant(SchemaVariant::Fragment)]
#[ExcludeFromVariant(SchemaVariant::Mutable)]
public string $internalNotes;
```

#### The IncludeOnlyInVariant Attribute

Use `#[IncludeOnlyInVariant]` to include a property only in specific variants. Properties with this attribute are excluded from all other variants (including `Full` unless specified):

```php
use Garden\Schema\Entity;
use Garden\Schema\IncludeOnlyInVariant;
use Garden\Schema\SchemaVariant;

class User extends Entity {
    public int $id;
    public string $username;
    public string $email;

    // Only include in Create schema (for initial user setup)
    #[IncludeOnlyInVariant(SchemaVariant::Create)]
    public ?string $initialPassword;

    // Include in both Create and Full schemas
    #[IncludeOnlyInVariant(SchemaVariant::Create, SchemaVariant::Full)]
    public ?string $inviteCode;
}

// Create schema includes initialPassword and inviteCode
$createSchema = User::getSchema(SchemaVariant::Create);

// Full schema includes inviteCode but NOT initialPassword
$fullSchema = User::getSchema(SchemaVariant::Full);

// Fragment and Mutable schemas include neither
$fragmentSchema = User::getSchema(SchemaVariant::Fragment);
```

> **Note:** If both `#[IncludeOnlyInVariant]` and `#[ExcludeFromVariant]` are present on the same property, `#[IncludeOnlyInVariant]` takes precedence.

#### Schema Variant Caching

Each schema variant is cached separately. You can invalidate caches at different levels:

```php
use Garden\Schema\Entity;
use Garden\Schema\SchemaVariant;

// Invalidate a specific variant for a class
Entity::invalidateSchemaCache(Article::class, SchemaVariant::Full);

// Invalidate all variants for a class
Entity::invalidateSchemaCache(Article::class);

// Invalidate all cached schemas globally
Entity::invalidateSchemaCache();
```

#### Nested Entities

Entities can reference other entities. Nested data is automatically validated and cast:

```php
class Address extends Entity {
    public string $street;
    public string $city;
}

class Person extends Entity {
    public string $name;
    public Address $address;
    public ?Address $workAddress = null;
}

$person = Person::from([
    'name' => 'Jane',
    'address' => ['street' => '123 Main St', 'city' => 'Springfield'],
]);

$person->address; // Address instance
$person->address->city; // 'Springfield'
```

#### ArrayObject Properties

Properties typed as `ArrayObject` (or subclasses) are mapped to `object` in the schema. Arrays are automatically converted to `ArrayObject` instances during validation:

```php
use Garden\Schema\Entity;

class Config extends Entity {
    public string $name;
    public \ArrayObject $settings;
    public ?\ArrayObject $metadata = null;
}

$config = Config::from([
    'name' => 'app',
    'settings' => ['debug' => true, 'timeout' => 30],
]);

$config->settings; // ArrayObject instance
$config->settings['debug']; // true
$config->settings['timeout'] = 60; // Modify in place
```

`ArrayObject` instances are preserved in `toArray()` and JSON serialization to ensure empty objects serialize as `{}` (JSON object) rather than `[]` (JSON array):

```php
$config = Config::from([
    'name' => 'app',
    'settings' => [],  // Empty
]);

json_encode($config); // {"name":"app","settings":{},"metadata":null}
```

#### DateTimeImmutable Properties

Properties typed as `DateTimeImmutable` are mapped to `string` with `format: date-time` in the schema. Date-time strings are automatically converted to `DateTimeImmutable` instances, and serialized to RFC3339 format (or RFC3339_EXTENDED if milliseconds are present):

```php
use Garden\Schema\Entity;

class Event extends Entity {
    public string $title;
    public \DateTimeImmutable $startsAt;
    public ?\DateTimeImmutable $endsAt = null;
}

$event = Event::from([
    'title' => 'Meeting',
    'startsAt' => '2024-06-15T14:00:00+00:00',
]);

$event->startsAt; // DateTimeImmutable instance
$event->startsAt->format('Y-m-d'); // '2024-06-15'

// toArray() and JSON serialize to RFC3339 format
$array = $event->toArray();
$array['startsAt']; // '2024-06-15T14:00:00+00:00'

// With milliseconds, uses RFC3339_EXTENDED
$event->startsAt = new \DateTimeImmutable('2024-06-15T14:00:00.123+00:00');
$array = $event->toArray();
$array['startsAt']; // '2024-06-15T14:00:00.123+00:00'
```

#### Using entityClassName in Schemas

You can reference entity classes directly in schema definitions, similar to `BackedEnum`:

```php
// Shorthand
$schema = Schema::parse([
    'user' => User::class,
]);

// Long form
$schema = Schema::parse([
    'user' => ['entityClassName' => User::class],
]);

// Array of entities
$schema = Schema::parse([
    'users:a' => User::class,
]);

$result = $schema->validate(['user' => ['name' => 'John', 'email' => 'j@x.com', 'age' => 25]]);
$result['user']; // User instance
```

#### Converting to Arrays and JSON

Entities implement `ArrayAccess` and `JsonSerializable`:

```php
$user = User::from(['name' => 'John', 'email' => 'john@example.com', 'age' => 30]);

// ArrayAccess for reading
$user['name']; // 'John'

// ArrayAccess for writing (bypasses validation - use with care)
$user['age'] = 31;

// Convert to array (nested entities become arrays, enums become values)
$array = $user->toArray();

// JSON serialization uses toArray()
$json = json_encode($user);

// Round-trip: array -> entity -> array produces equivalent data
$user2 = User::from($user->toArray());
```

> **Note:** Writing via `ArrayAccess` (e.g., `$user['age'] = 'not a number'`) and direct property assignment do NOT perform validation. Use `Entity::from()` for validated construction, or call `$entity->validate()` after modifications to verify the entity is valid.

#### Validating After Modifications

After modifying an entity directly, you can call `validate()` to verify it's still in a valid state:

```php
$user = User::from(['name' => 'John', 'email' => 'john@example.com', 'age' => 30]);

// Modify directly (no validation)
$user->name = 'Jane';
$user['age'] = 25;

// Validate current state - returns new validated entity or throws ValidationException
$validatedUser = $user->validate();

// Invalid modification
$user->age = 'not a number';
$user->validate(); // Throws ValidationException
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
    // You can specify nullable as a property attribute.
    'opt1:s?' => ['nullable' => true],

    // You can specify null as an optional type in the declaration.
    'opt2:s|n?' => 'Another nullable, optional property.'
]
```

### Default Values

You can specify a default value with the `default` attribute. If the value is omitted during validation then the default value will be used. Note that default values are not applied during sparse validation.

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

The `Validation` object and `ValidationException` both encode to a [specific format]('./open-api.json'). Here is an example:

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

## Schema Polymorphism

Schemas have some support for implementing schema polymorphism by letting you validate an object against different schemas depending on its value.

### The `discriminator` Property

The `discriminator` of a schema lets you specify an object property that specifies what type of object it is. That property is then used to reference a specific schema for the object. The discriminator has the following format:

```json5
{
    "discriminator": {
        "propertyName": "<string>", // Name of the property used to reference a schema.
        "mapping": {
          "<propertyValue1>": "<ref>", // Reference to a schema.
          "<propertyValue>": "<alias>" // Map a value to another value.
        }
    }
}
```

You can see above that the `propertyName` specifies which property is used as the discriminator. There is also an optional `mapping` property that lets you control how schemas are mapped to values. discriminators are resolved int he following way:

1. The property value is mapped using the mapping property.
2. If the value is a valid JSON reference then it is looked up. Only values in mappings can specify a JSON reference in this way.
3. If the value is not a valid JSON reference then it is is prepended with `#/components/schemas/` to make a JSON reference.

Here is an example at work:

```json5
{
  "discriminator": {
    "propertyName": "petType",
    "mapping": {
      "dog": "#/components/schemas/Dog", // A direct reference.
      "fido": "Dog" // An alias that will be turned into a reference.
    }
  }
}
```

### The `oneOf` Property

The `oneOf` property works in conjunction with the `discriminator` to limit the schemas that the object is allowed to validate against. If you don't specify `oneOf` then any schemas under `#/components/schemas` are fair game.

To use the `oneOf` property you must specify `$ref` nodes like so:

```json5
{
  "oneOf": [
    { "$ref": "#/components/schemas/Dog" },
    { "$ref": "#/components/schemas/Cat" },
    { "$ref": "#/components/schemas/Mouse" },
  ],
  "discriminator": {
    "propertyType": "species"
  }
}
```

In the above example the "species" property will be used to construct a reference to a schema. That reference must match one of the references in the `oneOf` property.

*If you are familiar with with OpenAPI spec please note that inline schemas are not currently supported for oneOf in Garden Schema.*


## Validation Options

Both **validate()** and **isValid()** can take an additional **$options** argument which modifies the behavior of the validation slightly, depending on the option.

### The `request` Option

You can pass an option of `['request' => true]` to specify that you are validating request data. When validating request data, properties that have been marked as `readOnly: true` will be treated as if they don't exist, even if they are marked as required.

### The `response` Option

You can pass an option of `['response' => true]` to specify that you are validating response data. When validating response data, properties that have been marked as `writeOnly: true` will be treated as if they don't exist, even if they are marked as required.

### The `sparse` Option

You can pass an option of `['sparse' => true]` to specify a sparse validation. When you do a sparse validation, missing properties do not give errors and the sparse data is returned. Sparse validation allows you to use the same schema for inserting vs. updating records. This is common in databases or APIs with POST vs. PATCH requests.

## Flags

Flags can be applied a schema to change it's inherit validation.

```php
use Garden\Schema\Schema;
$schema = Schema::parse([]);

// Enable a flag.
$schema->setFlag(Schema::VALIDATE_STRING_LENGTH_AS_UNICODE, true);

// Disable a flag.
$schema->setFlag(Schema::VALIDATE_STRING_LENGTH_AS_UNICODE, false);

// Set all flags together.
$schema->setFlags(Schema::VALIDATE_STRING_LENGTH_AS_UNICODE & Schema::VALIDATE_EXTRA_PROPERTY_NOTICE);

// Check if a flag is set.
$schema->hasFlag(Schema::VALIDATE_STRING_LENGTH_AS_UNICODE); // true
```

### `VALIDATE_STRING_LENGTH_AS_UNICODE`

By default, schema's validate str lengths in terms of bytes. This is useful because this is the common
unit of storage for things like databases.

Some unicode characters take more than 1 byte. An emoji like 😱 takes 4 bytes for example.

Enable this flag to validate unicode character length instead of byte length.

### `VALIDATE_EXTRA_PROPERTY_NOTICE`

Set this flag to trigger notices whenever a validated object has properties not defined in the schema.

### `VALIDATE_EXTRA_PROPERTY_EXCEPTION`

Set this flag to throw an exception whenever a validated object has properties not defined in the schema.

## Custom Validation with addValidator()

You can customize validation with `Schema::addValidator()`. This method lets you attach a callback to a schema path. The callback has the following form:

```php
function (mixed $value, ValidationField $field): bool {
}
```

The callback should `true` if the value is valid or `false` otherwise. You can use the provided `ValidationField` to add custom error messages.

## Filtering Data

You can filter data before it is validating using `Schema::addFilter()`. This method lets you filter data at a schema path. The callback has the following form:

```php
function (mixed $value, ValidationField $field): mixed {
}
```

The callback should return the filtered value. Filters are called before validation occurs so you can use them to clean up date you know may need some extra processing.

The `Schema::addFilter()` also accepts  `$validate` parameter that allows your filter to validate the data and bypass default validation. If you are validating date in this way you can add custom errors to the `ValidationField` parameter and return `Invalid::value()` your validation fails.

### Format Filters

You can also filter all fields with a particular format using the `Schema::addFormatFilter()`. This method works similar to `Schema::addFilter()` but it applies to all fields that match the given `format`. You can even use format filters to override default format processing.

```php
$schema = new Schema([...]);

// By default schema returns instances of DateTimeImmutable, instead return a string.
$schema->addFormatFilter('date-time', function ($v) {
    $dt = new \DateTime($v);
    return $dt->format(\DateTime::RFC3339);
}, true);
```

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

| Property                                                                                            | Applies To | Notes                                                                                                                                     |
|-----------------------------------------------------------------------------------------------------| ---------- |-------------------------------------------------------------------------------------------------------------------------------------------|
| [allOf](http://json-schema.org/latest/json-schema-validation.html#rfc.section.6.7.1)                | Schema[] | An instance validates successfully against this keyword if it validates successfully against all schemas defined by this keyword's value. |
| [multipleOf](http://json-schema.org/latest/json-schema-validation.html#rfc.section.6.2.1)           | integer/number | A numeric instance is only valid if division by this keyword's value results in an integer.                                               |
| [maximum](http://json-schema.org/latest/json-schema-validation.html#rfc.section.6.2.2)              | integer/number | If the instance is a number, then this keyword validates only if the instance is less than or exactly equal to "maximum".                 |
| [exclusiveMaximum](http://json-schema.org/latest/json-schema-validation.html#rfc.section.6.2.3)     | integer/number | If the instance is a number, then the instance is valid only if it has a value strictly less than (not equal to) "exclusiveMaximum".      |
| [minimum](http://json-schema.org/latest/json-schema-validation.html#rfc.section.6.2.4)              | integer/number | If the instance is a number, then this keyword validates only if the instance is greater than or exactly equal to "minimum".              |
| [exclusiveMinimum](http://json-schema.org/latest/json-schema-validation.html#rfc.section.6.2.5)     | integer/number | If the instance is a number, then the instance is valid only if it has a value strictly greater than (not equal to) "exclusiveMinimum".   |
| [maxLength](http://json-schema.org/latest/json-schema-validation.html#rfc.section.6.6)              | string | Limit the unicode character length of a string.                                                                                           |
| [minLength](http://json-schema.org/latest/json-schema-validation.html#rfc.section.6.7)              | string | Minimum length of a string.                                                                                                               |
| maxByteLength                                                                                       | string | Maximum byte length of the the property.                                                                                                  |
| [pattern](http://json-schema.org/latest/json-schema-validation.html#rfc.section.6.8)                | string | A regular expression without delimiters. You can add a custom error message with the `x-patternMessageCode` field.                        |
| [items](http://json-schema.org/latest/json-schema-validation.html#rfc.section.6.9)                  | array | Ony supports a single schema.                                                                                                             |
| [maxItems](http://json-schema.org/latest/json-schema-validation.html#rfc.section.6.11)              | array | Limit the number of items in an array.                                                                                                    |
| [minItems](http://json-schema.org/latest/json-schema-validation.html#rfc.section.6.12)              | array | Minimum number of items in an array.                                                                                                      |
| [uniqueItems](http://json-schema.org/latest/json-schema-validation.html#rfc.section.6.4.5)          | array | All items must be unique.                                                                                                                 |
| [maxProperties](http://json-schema.org/latest/json-schema-validation.html#rfc.section.6.5.1)        | object | Limit the number of properties on an object.                                                                                              |
| [minProperties](http://json-schema.org/latest/json-schema-validation.html#rfc.section.6.5.2)        | object | Minimum number of properties on an object.                                                                                                |
| [additionalProperties](http://json-schema.org/latest/json-schema-validation.html#rfc.section.6.5.6) | object | Validate additional properties against a schema. Can also be **true** to always validate.                                                 |
| [required](http://json-schema.org/latest/json-schema-validation.html#rfc.section.6.17)              | object | Names of required object properties.                                                                                                      |
| [properties](http://json-schema.org/latest/json-schema-validation.html#rfc.section.6.18)            | object | Specify schemas for object properties.                                                                                                    |
| [enum](http://json-schema.org/latest/json-schema-validation.html#rfc.section.6.23)                  | any | Specify an array of valid values.                                                                                                         |
| [type](http://json-schema.org/latest/json-schema-validation.html#rfc.section.6.25)                  | any | Specify a type of an array of types to validate a value.                                                                                  |
| [default](http://json-schema.org/latest/json-schema-validation.html#rfc.section.7.3)                | object | Applies to a schema that is in an object property.                                                                                        |
| [format](http://json-schema.org/latest/json-schema-validation.html#rfc.section.8.3)                 | string | Support for date-time, email, ipv4, ipv6, ip, uri.                                                                                        |
| [oneOf](http://json-schema.org/latest/json-schema-validation.html#rfc.section.6.7.3)                | object | Works with the `discriminator` property to validate against a dynamic schema.                                                             |

## OpenAPI Schema Support

OpenAPI defines some extended properties that are applied during validation.

| Property | Type | Notes |
| -------- | ---- | ----- |
| nullable | boolean | If a field is nullable then it can also take the value **null**. |
| readOnly | boolean | Relevant only for Schema "properties" definitions. Declares the property as "read only". This means that it MAY be sent as part of a response but SHOULD NOT be sent as part of the request. If the property is marked as readOnly being true and is in the required list, the required will take effect on the response only. |
| writeOnly | boolean |  Relevant only for Schema "properties" definitions. Declares the property as "write only". Therefore, it MAY be sent as part of a request but SHOULD NOT be sent as part of the response. If the property is marked as writeOnly being true and is in the required list, the required will take effect on the request only. |
| [discriminator](https://github.com/OAI/OpenAPI-Specification/blob/master/versions/3.0.0.md#discriminatorObject) | object | Validate against a dynamic schema based on a property value. |
