<?php

namespace Core\Http\Exception;

/**
 * Page forbidden exception class.
 *
 * @class ForbiddenException
 * @package \Core\Http\Exception
 */
class ForbiddenException extends HttpException
{
    protected static $pesan = 'Forbidden';

    public function __toString(): string
    {
        $this->respond(403);
        return parent::__toString();
    }
}
