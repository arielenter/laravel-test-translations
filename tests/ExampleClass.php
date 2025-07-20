<?php

namespace Tests;

use Exception;

abstract class ExampleClass
{
    static public function notTrue(bool $value): string
    {
        if ($value != false) {
            throw new Exception(__('error.wrong', [ 'value' => 'true' ]));
        }

        return 'All good!';
    }
}
