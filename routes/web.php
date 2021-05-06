<?php

use Devio\Pipedrive\Pipedrive;
use Triibo\Mautic\Facades\Mautic;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Console\Input\Input;

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

Auth::routes([
    'register' => false,
    'confirm'  => false,
    'reset'    => false,
]);

Route::get('/', function () {
    return view('welcome');
});

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

// Teste de api mautic
Route::get('/mautic/{class}', [App\Http\Controllers\HomeController::class, 'teste'])->name('teste');

// Teste de api pipedrive
Route::get('/pipe',           [App\Http\Controllers\HomeController::class, 'teste2'])->name('teste2');
// Route::get('/pipedrive/{class}', function ($class) {
//     $token     = config('pipedrive.token');
//     $pipedrive = new Pipedrive($token);
//     $response  = $pipedrive->$class->all();
//     $data      = $response->getData();
//     dd($data);
// });


Route::any('/teste', [App\Http\Controllers\HomeController::class, 'teste3'])->name('teste3');
