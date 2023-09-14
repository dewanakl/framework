<?php

namespace Core\Facades\Exception;

use Exception;
use Psr\Container\ContainerExceptionInterface;

/**
 * Container exception class.
 *
 * @class ContainerException
 * @package \Core\Facades\Exception
 */
class ContainerException extends Exception implements ContainerExceptionInterface
{
    //
}
