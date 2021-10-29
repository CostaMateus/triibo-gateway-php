<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\CofryController;
use App\Http\Controllers\ChatGuruController;
use App\Http\Controllers\PipedriveWebhookController;

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
//     Route::post("/company", [MauticWebhookController::class, "company"])->name("mautic.company");
//     Route::post("/contact", [MauticWebhookController::class, "contact"])->name("mautic.contact");
//
// });

/**
 * Integração Pipedrive => Mautic
 */
Route::prefix("pipedrive")->group(function () {

    // Route::prefix("organization")->group(function () {
    //     Route::post("/added",   [PipedriveWebhookController::class, "organization"])->name("pipe.org.added");
    //     Route::post("/updated", [PipedriveWebhookController::class, "organization"])->name("pipe.org.updated");
    //     Route::post("/deleted", [PipedriveWebhookController::class, "organization"])->name("pipe.org.deleted");
    // });

    Route::post("/organization",  [PipedriveWebhookController::class, "organization"])->name("pipe.organization");
    Route::post("/person",        [PipedriveWebhookController::class, "person"])->name("pipe.person");
    Route::post("/stage",         [PipedriveWebhookController::class, "stage"])->name("pipe.stage");
    Route::post("/deal",          [PipedriveWebhookController::class, "deal"])->name("pipe.deal");

});

// Route::prefix("common")->group(function () {
//
//     Route::get("/synchronizeids", [PipedriveWebhookController::class, "syncIdsMauticPipe"])->name("synchronize.ids");
//     Route::get("/importstages",   [PipedriveWebhookController::class, "importAllStages"])->name("import.all.stages");
//
// });



/**
 * Integração Triibo <> Cofry
 */
// Route::prefix("cofry")->group(function () {
//
//     // Rotas para testes individuais
//     Route::get("/estabelecimentos",  [CofryController::class, "estabelecimentos"])->name("cofry.estabelecimentos");
//     Route::get("/promocoes",         [CofryController::class, "promocoes"])->name("cofry.promocoes");
//     Route::get("/imagens",           [CofryController::class, "imagens"])->name("cofry.imagens");
//
//     // Processa estaabelecimentos e promoções
//     Route::get("/index",             [CofryController::class, "index"])->name("cofry.index");
//
//     // Processa as imagens
//     Route::get("/uploadImages",      [CofryController::class, "uploadImages"])->name("cofry.uploadImages");
//
// });


/**
 * Integração Triibo => ChatGuru
 */
Route::prefix("triibo")->group(function () {

    Route::post("/chatguru", [ChatGuruController::class, "index"])->name("triibo.chatguru");

});




// TESTES
Route::post("/gateway/teste", [HomeController::class, 'gateway'])->name('gateway');
