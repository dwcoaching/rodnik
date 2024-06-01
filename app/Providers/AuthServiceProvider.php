<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        'App\Models\Report' => 'App\Policies\ReportPolicy',
        'App\Models\Spring' => 'App\Policies\SpringPolicy',
        'App\Models\Overpass' => 'App\Policies\OverpassBatchPolicy',
        'App\Models\Job' => 'App\Policies\JobPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        Gate::define('admin', function (User $user) {
            return $user->is_admin;
        });

        Gate::define('superadmin', function (User $user) {
            return $user->is_superadmin;
        });
    }
}
