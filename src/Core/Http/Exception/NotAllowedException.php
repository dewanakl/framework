<?php

namespace Core\Http\Exception;

/**
 * Page not allowed exception class.
 *
 * @class NotAllowedException
 * @package \Core\Http\Exception
 */
class NotAllowedException extends HttpException
{
    protected static $pesan = 'Method Not Allowed';

    public function __toString(): string
    {
        $this->respond(405);
        return parent::__toString();
    }
}
