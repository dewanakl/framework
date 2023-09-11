<?php

namespace Core\Http\Exception;

/**
 * Page not found exception class.
 *
 * @class NotFoundException
 * @package \Core\Http\Exception
 */
class NotFoundException extends HttpException
{
    protected static $pesan = 'Not Found';

    public function __toString(): string
    {
        $this->respond(404);
        return parent::__toString();
    }
}
