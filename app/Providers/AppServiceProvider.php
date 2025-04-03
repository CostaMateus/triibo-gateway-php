<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        if ( $this->app->environment() !== "production" )
            $this->app->register( IdeHelperServiceProvider::class );
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Model::shouldBeStrict( !$this->app->isProduction() );
        DB::prohibitDestructiveCommands( $this->app->isProduction() );
    }
}
