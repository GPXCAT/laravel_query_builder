<?php

namespace Gpxcat\LaravelQueryBuilder;

use Exception;
use Illuminate\Http\Request;

class QueryBuilderException extends Exception
{
    public function __construct($message, int $code = 403)
    {
        parent::__construct($message, $code);
    }

    public function render(Request $request)
    {
    }
}
