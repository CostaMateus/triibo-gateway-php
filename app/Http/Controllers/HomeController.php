<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Contracts\Support\Renderable;

class HomeController extends Controller
{
    /**
     * Show the application welcome.
     *
     * @return  Renderable
     */
    public function welcome()
    {
        return view( "welcome", [ "showToast" => false, "message" => "" ] );
    }

    /**
     * Clear the application caches.
     *
     * @return  Renderable
     */
    public function clearCache()
    {
        Artisan::call( "optimize:clear" );

        return view( "welcome", [ "showToast" => true, "message" => "Cache limpos com sucesso!" ] );
    }

    /**
     * Show the application dashboard.
     *
     * @return  Renderable
     */
    public function index()
    {
        return view( "home" );
    }

    /**
     * Show the documentation.
     *
     * @return  Renderable
     */
    public function documentation()
    {
        return view( "doc_parts.index" );
    }

    /**
     * Git push.
     *
     * @return  RedirectResponse
     */
    public function gitPush() : RedirectResponse
    {
        shell_exec( "/var/www/html/laravel/laravel-deploy.sh" );

        return redirect()->back();
    }
}
