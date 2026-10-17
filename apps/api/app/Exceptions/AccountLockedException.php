<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class AccountLockedException extends HttpException
{
    public function __construct(string $message = '账号已被锁定，请稍后再试。')
    {
        parent::__construct(423, $message);
    }
}
