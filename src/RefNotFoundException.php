<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema;

use Throwable;

/**
 * An exception that represents a reference not being found.
 */
class RefNotFoundException extends \Exception {
    /**
     * RefNotFoundException constructor.
     *
     * @param string $message The error message.
     * @param int $number The error number.
     * @param Throwable|null $previous The previous exeption.
     */
    public function __construct(string $message = "", $number = 404, Throwable $previous = null) {
        parent::__construct($message, $number, $previous);
    }
}
