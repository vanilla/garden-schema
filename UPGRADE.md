# Upgrade Guide

## Version 1 to Version 2

### New Features

- The `nullable` schema property has been added to allow a value to also be null.

### Deprecations

The following deprecations will throw deprecation notices, but still work in version 2, but will be removed in the next major version.

- Schemas with multiple types are deprecated. The `nullable` property has been added to schemas to allow a type to also be null which should suit most purposes.
