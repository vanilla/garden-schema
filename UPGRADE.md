# Upgrade Guide

## Version 1 to Version 2

### New Features

- The `nullable` schema property has been added to allow a value to also be null.

### Deprecations

The following deprecations will throw deprecation notices, but still work in version 2, but will be removed in the next major version.

- Schemas with multiple types are deprecated. The `nullable` property has been added to schemas to allow a type to also be null which should suit most purposes.

- `Schema::validate()` and `Schema::isValid()` no longer take the `$sparse` parameter. Instead, pass an array with `['sparse' => true]` to do the same thing. Right now the boolean is still supported, but will be removed in future versions.

- Specifying a type of "datetime" is deprecated. Replace it with a type of "string" and a format of "date-time". This also introduces a backwards incompatibility.

- Specifying a type of "timestamp" is deprecated. Replace it with a type of "integer" and a format of "timestamp". This also introduces a backwards incompatibility. 

### Backwards Incompatibility

- Protected methods of the `Schema` class have changed signatures.

- The "datetime" type has been removed and replaced with the standard "string" type and a "date-time" format. The format still still returns `DateTime` instances though so having an explicit type of "string" with a "date-time" format now returns a different type.

- The "timestamp" type has been removed and replaced with the standard "integer" type and a "timestamp" format. A short type of "ts" is still supported, but is now converted to the aforementioned type/format combination.
