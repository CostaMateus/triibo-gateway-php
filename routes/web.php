<?php

use Illuminate\Support\Facades\Route;

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

// Route::get('/contact', [App\Http\Controllers\MauticWebhookController::class, 'index'])->name('getContact');

Route::get('/contacts/{id?}', function ($id = null) {
    if ($id)
        return Mautic::request('GET','contacts/'.$id);
    else
        return Mautic::request('GET','contacts');
});