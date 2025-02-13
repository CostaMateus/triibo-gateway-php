<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

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

    protected $allowedDomains = [
        "triibo.com.br"
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        Validator::extend( "allowed_domain", function( $attribute, $value, $parameters, $validator ) {
            return in_array( explode( "@", $value )[ 1 ], $this->allowedDomains );
        }, __( "validation.custom.allowed_domain" ) );

        Gate::define( "viewLogViewer", function ( ?User $user )
        {
            return true;
        } );
    }
}
