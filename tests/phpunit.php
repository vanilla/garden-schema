<?php
// PHPUnit v6 backwards compatibility with v5 error classes
if (!class_exists('PHPUnit_Framework_Error_Notice') && class_exists('PHPUnit\\Framework\\Error\\Notice')) {
    class_alias('PHPUnit\\Framework\\Error\\Notice', 'PHPUnit_Framework_Error_Notice');
}

require('vendor/autoload.php');
