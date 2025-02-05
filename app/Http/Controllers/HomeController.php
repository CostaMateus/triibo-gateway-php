<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;

class HomeController extends Controller
{
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
