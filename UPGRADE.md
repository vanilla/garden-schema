# Upgrade Guide

## Version 1 to Version 2

With version 2, Garden Schema is moving away from JSON Schema and towards Open API. Since Open API is base on JSON Schema this isn't a major transition, but there are some differences.

### Bug Fixes

- Properties with colons in them no longer throw an invalid type exception.
- Fixed bug where nested schema objects were not getting their default values respected. 

### New Features

- The `nullable` schema property has been added to allow a value to also be null.
- The `readOnly` and `writeOnly` properties are now supported. To use them in validation pass either `['request' => true]` or `['response' => true]` as options to one of the schema validation functions.
- Support for the following validation properties has been added: `multipleOf`, `maximum`, `exclusiveMaximum`, `minimum`, `exclusiveMinimum`, `uniqueItems`, `maxProperties`, `minProperties`, `additionalProperties`.
- Schemas now support references with the `$ref` attribute! To use references you can use `Schema::setRefLookup()` with the built in `ArrayRefLookup` class.
- You can now create custom `Validation` instances by using a custom `Schema::setValidationFactory()` method. This is much more flexible than the deprecated `Schema::setValidationClass()` method.

### Deprecations

The following deprecations will throw deprecation notices, but still work in version 2, but will be removed in the next major version.

- Schemas with multiple types are deprecated. The `nullable` property has been added to schemas to allow a type to also be null which should suit most purposes.

- `Schema::validate()` and `Schema::isValid()` no longer take the `$sparse` parameter. Instead, pass an array with `['sparse' => true]` to do the same thing. Right now the boolean is still supported, but will be removed in future versions.

- Specifying a type of `datetime` is deprecated. Replace it with a type of `string` and a format of `date-time`. This also introduces a backwards incompatibility.

- Specifying a type of `timestamp` is deprecated. Replace it with a type of `integer` and a format of `timestamp`. This also introduces a backwards incompatibility. 

- Specifying schema paths separated with `.` is now deprecated and will trigger a deprecated error. Paths should now be separated with `/` in `Schema::addFilter()`, `Schema::addValidator()`, `Schema::getField()`, and `Schema::setField()`.

- The `Schema::setValidationClass()` and `Schema::getValidationClass()` methods are deprecated. Use the new `Schema::setValidationFactory()` and `Schema::getValidationFactory()` instead.

### Backwards Incompatibility

- Protected methods of the `Schema` class have changed signatures.

- The `datetime` type has been removed and replaced with the standard `string` type and a `date-time` format. The format still returns `DateTimeInterface` instances though so having an explicit type of `string` with a `date-time` format now returns a different type.

- The `timestamp` type has been removed and replaced with the standard `integer` type and a `timestamp` format. A short type of `ts` is still supported, but is now converted to the aforementioned type/format combination.

- Paths in validation results are now seperated by `/` instead of `.` to more closely follow the JSON ref spec.

- When specifying paths with `Schema::addValidator()` and `Schema::addFilter()` you should separate paths with `/` instead of `.` and specify the full schema path you want to add your callback to. So for example: `foo.bar[]` would become `properties/foo/properties/bar/items`. Currently, the schema will attempt to fix some old format validators and trigger a deprecation error, but may not catch every edge case.

- The `Validation` class now has type hints. You will need to update subclasses to avoid exceptions.
