<?php

declare(strict_types = 1);

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register() : void
    {
        if ( $this->app->environment() !== "production" )
            $this->app->register( IdeHelperServiceProvider::class );
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot() : void
    {
        Model::shouldBeStrict( ! $this->app->isProduction() );
        DB::prohibitDestructiveCommands( $this->app->isProduction() );
    }
}
