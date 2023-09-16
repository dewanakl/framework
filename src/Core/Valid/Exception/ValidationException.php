<?php

namespace Core\Valid\Exception;

use Core\Http\Request;
use Core\Http\Session;
use Core\Valid\Validator;
use Exception;

/**
 * Validator exception class.
 *
 * @class ValidationException
 * @package \Core\Valid\Exception
 */
class ValidationException extends Exception
{
    /**
     * Init object.
     *
     * @param Request $request
     * @param Validator $validator
     * @return void
     */
    public function __construct(Request $request, Validator $validator)
    {
        session()->set(Session::OLD, $request->all());
        session()->set(Session::ERROR, $validator->failed());
        respond()->redirect(session()->get(Session::ROUTE, '/'));
    }
}
