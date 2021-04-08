<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Route::middleware('auth:api')->get('/user', function (Request $request) {
//     return $request->user();
// });


/**
 * Integração Mautic <> Pipedrive
 */
Route::prefix('mautic')->group(function () {

    Route::post('/company', [App\Http\Controllers\MauticWebhookController::class, 'company'])->name('mautic.company');
    Route::post('/contact', [App\Http\Controllers\MauticWebhookController::class, 'contact'])->name('mautic.contact');

});
Route::prefix('pipedrive')->group(function () {

    Route::post('/organization', [App\Http\Controllers\PipedriveWebhookController::class, 'organization'])->name('pipe.organization');
    Route::post('/person',       [App\Http\Controllers\PipedriveWebhookController::class, 'person'])->name('pipe.person');
    Route::post('/stage',        [App\Http\Controllers\PipedriveWebhookController::class, 'stage'])->name('pipe.stage');
    Route::post('/deal',         [App\Http\Controllers\PipedriveWebhookController::class, 'deal'])->name('pipe.deal');

    Route::get('/updatePipedrive', [App\Http\Controllers\PipedriveWebhookController::class, 'updatePipedrive'])->name('pipe.updatePipedrive');
});


/**
 * Integração Triibo <> Cofry
 */
Route::prefix('cofry')->group(function () {

    Route::get('/index',  [App\Http\Controllers\CofryController::class, 'index'])->name('cofry.index');

});