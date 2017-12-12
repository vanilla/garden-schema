<?php
/**
 * @author Alexandre (DaazKu) Chouinard <alexandre.c@vanillaforums.com>
 * @copyright 2009-2017 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema\Tests\Fixtures;

use Garden\Schema\Schema;
use Garden\Schema\ValidationField;
use Garden\Schema\ValidationException;

/**
 * A Schema that throws a validation error from itself.
 */
class SchemaValidationFail extends Schema {
    /**
     * {@inheritdoc}
     */
    public function validate($data, $sparse = false) {
        $field = new ValidationField($this->createValidation(), $this->getSchemaArray(), '', $sparse);
        $field->addError('invalid', ['messageCode' => '{field} is always invalid.']);
        throw new ValidationException($field->getValidation());
    }
}
