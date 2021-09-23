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

// Route::middleware("auth:api")->get("/user", function (Request $request) {
//     return $request->user();
// });


/**
 * Integração Mautic => Pipedrive
 */
// Route::prefix("mautic")->group(function () {
//
//     Route::post("/company", [App\Http\Controllers\MauticWebhookController::class, "company"])->name("mautic.company");
//     Route::post("/contact", [App\Http\Controllers\MauticWebhookController::class, "contact"])->name("mautic.contact");
//
// });

/**
 * Integração Pipedrive => Mautic
 */
Route::prefix("pipedrive")->group(function () {

    // Route::prefix("organization")->group(function () {
    //     Route::post("/added",   [App\Http\Controllers\PipedriveWebhookController::class, "organization"])->name("pipe.org.added");
    //     Route::post("/updated", [App\Http\Controllers\PipedriveWebhookController::class, "organization"])->name("pipe.org.updated");
    //     Route::post("/deleted", [App\Http\Controllers\PipedriveWebhookController::class, "organization"])->name("pipe.org.deleted");
    // });

    Route::post("/organization",  [App\Http\Controllers\PipedriveWebhookController::class, "organization"])->name("pipe.organization");
    Route::post("/person",        [App\Http\Controllers\PipedriveWebhookController::class, "person"])->name("pipe.person");
    Route::post("/stage",         [App\Http\Controllers\PipedriveWebhookController::class, "stage"])->name("pipe.stage");
    Route::post("/deal",          [App\Http\Controllers\PipedriveWebhookController::class, "deal"])->name("pipe.deal");

});

// Route::prefix("common")->group(function () {
//
//     Route::get("/synchronizeids", [App\Http\Controllers\PipedriveWebhookController::class, "syncIdsMauticPipe"])->name("synchronize.ids");
//     Route::get("/importstages",   [App\Http\Controllers\PipedriveWebhookController::class, "importAllStages"])->name("import.all.stages");
//
// });



/**
 * Integração Triibo <> Cofry
 */
// Route::prefix("cofry")->group(function () {
//
//     // Rotas para testes individuais
//     Route::get("/estabelecimentos",  [App\Http\Controllers\CofryController::class, "estabelecimentos"])->name("cofry.estabelecimentos");
//     Route::get("/promocoes",         [App\Http\Controllers\CofryController::class, "promocoes"])->name("cofry.promocoes");
//     Route::get("/imagens",           [App\Http\Controllers\CofryController::class, "imagens"])->name("cofry.imagens");
//
//
//     // Processa estaabelecimentos e promoções
//     Route::get("/index",             [App\Http\Controllers\CofryController::class, "index"])->name("cofry.index");
//
//     // Processa as imagens
//     Route::get("/uploadImages",      [App\Http\Controllers\CofryController::class, "uploadImages"])->name("cofry.uploadImages");
//
// });


Route::post("/gateway/teste", [App\Http\Controllers\HomeController::class, 'gateway'])->name('gateway');
