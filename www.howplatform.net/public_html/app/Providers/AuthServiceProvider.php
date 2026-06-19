<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Auth;
use App\Auth\GenericUserProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [];

    public function boot(): void
    {
        $this->registerPolicies();

        // تعريف مزود "generic" ليستغني عن جدول users
        Auth::provider('generic', function ($app, array $config) {
            return new GenericUserProvider();
        });
    }
}
