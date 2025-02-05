<?php

namespace App\Http\Controllers;

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
        return view( "documentation" );
    }
}
