<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use App\Http\Integrations\MauticIntegration;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Auth::routes();

Route::get('/', function () {
    return view('welcome', [
        'showToast' => false,
        'message'   => ''
    ]);
});

Route::get('/home', function () {
    return view('home');
})->middleware('auth');

Route::get('/clear-caches', function() {
    Artisan::call('optimize:clear');
    return view('welcome', [
        'showToast' => true,
        'message'   => __('marketplace.cache')
    ]);
})->middleware('auth');

Route::get('/import-stages', function() {
    MauticIntegration::importAllStages();
    return view('welcome', [
        'showToast' => true,
        'message'   => __('marketplace.import.all.stages')
    ]);
})->middleware('auth');



// Route::get('/index', [App\Http\Controllers\HomeController::class, 'index'])->name('index');
// // Teste de api mautic
// Route::get('/mautic/{class}', [App\Http\Controllers\HomeController::class, 'teste'])->name('teste');
// // Teste de api pipedrive
// Route::get('/pipe',           [App\Http\Controllers\HomeController::class, 'teste2'])->name('teste2');
// // Route::get('/pipedrive/{class}', function ($class) {
// //     $token     = config('pipedrive.token');
// //     $pipedrive = new Pipedrive($token);
// //     $response  = $pipedrive->$class->all();
// //     $data      = $response->getData();
// //     dd($data);
// // });
// Route::any('/teste', [App\Http\Controllers\HomeController::class, 'teste3'])->name('teste3');
