<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken;

class PersonalAccessTokenModel extends PersonalAccessToken
{
    protected $table = 'admin_personal_access_tokens';
}
