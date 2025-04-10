<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Contracts\Support\Renderable;
use Symfony\Component\Process\Exception\ProcessFailedException;

class HomeController extends Controller
{
    /**
     * Show the application welcome.
     *
     * @return  Renderable
     */
    public function welcome() : Renderable
    {
        return view( "welcome", [ "showToast" => false, "message" => "" ] );
    }

    /**
     * Clear the application caches.
     *
     * @return  Renderable
     */
    public function clearCache() : Renderable
    {
        Artisan::call( "optimize:clear" );

        return view( "welcome", [ "showToast" => true, "message" => "Cache limpos com sucesso!" ] );
    }

    /**
     * Show the application dashboard.
     *
     * @return  Renderable
     */
    public function index() : Renderable
    {
        return view( "home" );
    }

    /**
     * Show the documentation.
     *
     * @return  Renderable
     */
    public function documentation() : Renderable
    {
        return view( "doc_parts.index" );
    }

    /**
     * Git push.
     *
     * @return  Renderable
     */
    public function gitPush() : Renderable
    {
        if ( !auth()->check() || auth()->user()->email !== "mateus.costa@triibo.com.br" )
            return view( "home" );

        try
        {
            $process = new Process( [ "/var/www/html/laravel/laravel-deploy.sh" ] );
            $process->setTimeout( 3600 );
            $process->run();

            if ( !$process->isSuccessful() )
                throw new ProcessFailedException( $process );

            return view( "home", [
                "showToast" => true,
                "message"   => "Git push executado com sucesso!",
            ] );
        }
        catch ( Exception $e )
        {
            return view( "home", [
                "showToast" => true,
                "message" => $e->getMessage(),
            ] );
        }
    }
}
