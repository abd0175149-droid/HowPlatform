<?php

namespace App\Auth;

use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

class GenericUserProvider implements UserProvider
{
    public function retrieveById($identifier) { return null; }
    public function retrieveByToken($identifier, $token) { return null; }
    public function updateRememberToken(Authenticatable $user, $token): void {}
    public function retrieveByCredentials(array $credentials) { return null; }
    public function validateCredentials(Authenticatable $user, array $credentials): bool { return false; }
    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): Authenticatable
    {return $user;}
}
