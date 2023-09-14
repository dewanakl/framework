<?php

namespace Core\Facades\Exception;

use Exception;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Not found exception class.
 *
 * @class NotFoundException
 * @package \Core\Facades\Exception
 */
class NotFoundException extends Exception implements NotFoundExceptionInterface
{
    //
}
