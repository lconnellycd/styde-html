<?php

namespace Styde\Html\Facades;

use Illuminate\Support\Facades\Facade;

class Html extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'html';
    }
}
