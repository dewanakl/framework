<?php

namespace Core\Http\Exception;

/**
 * Page expired exception class.
 *
 * @class ExpiredException
 * @package \Core\Http\Exception
 */
class ExpiredException extends HttpException
{
    protected static $pesan = 'Page Expired';

    public function __toString(): string
    {
        $this->respond(400);
        return parent::__toString();
    }
}
