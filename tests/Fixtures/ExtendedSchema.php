<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2017 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema\Tests\Fixtures;

use Garden\Schema\Schema;

/**
 * A basic subclass of Schema.
 */
class ExtendedSchema extends Schema {

    /** @var string */
    public $controller;

    /** @var string */
    public $method;

    /** @var string */
    public $type;

    /**
     * ExtendedSchema constructor.
     *
     * @param array $schema
     * @param string $controller
     * @param string $method
     * @param string $type
     */
    public function __construct(array $schema = [], $controller = null, $method = null, $type = null) {
        parent::__construct($schema);

        $this->controller = $controller;
        $this->method = $method;
        $this->type = $type;
    }
}
