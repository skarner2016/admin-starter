<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class AccountLockedException extends HttpException
{
    public function __construct(string $message = 'Account locked.')
    {
        parent::__construct(423, $message);
    }
}
