<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();
       //added passport on routes 
        Passport::routes();

        // Mandatory to define Scope
        Passport::tokensCan([
            'admin' => 'Add/Edit/Delete Users',
            'team' => 'Add/Edit Users',
            'basic' => 'List Users'
        ]);

        Passport::setDefaultScope([
            'basic'
        ]);
      
    }
}
