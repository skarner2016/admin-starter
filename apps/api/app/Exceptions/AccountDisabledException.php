<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class AccountDisabledException extends HttpException
{
    public function __construct(string $message = '账号已被停用。')
    {
        parent::__construct(403, $message);
    }
}
