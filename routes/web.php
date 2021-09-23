<?php

use App\Models\User;
use Mautic\MauticApi;
use Mautic\Auth\ApiAuth;
use Devio\Pipedrive\Pipedrive;
use Triibo\Mautic\Facades\Mautic;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\UserController;
use App\Notifications\WebhookMarketplace;
use Triibo\Mautic\Models\MauticConsumer;
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

Auth::routes([
    "register" => false
]);

Route::get("/", function () {
    return view("welcome", [
        "showToast" => false,
        "message"   => ""
    ]);
})->name("welcome");

Route::group(["middleware" => "auth"], function() {

    Route::get("/home", function () { return view("home"); })->name("home");

    Route::get("/clear-caches", function() {
        Artisan::call("optimize:clear");
        return view("welcome", [
            "showToast" => true,
            "message"   => __("marketplace.cache")
        ]);
    })->name("clear.caches");

    Route::get("/update-stages", function() {
        MauticIntegration::updateStages();
        return view("welcome", [
            "showToast" => true,
            "message"   => __("marketplace.update.all.stages")
        ]);
    })->name("update.stages");

    Route::resource("users", UserController::class);

});





// Route::get('/mautic', [App\Http\Controllers\HomeController::class, 'index']);

// Route::get('/pipedrive', function () {
//     $token     = config('pipedrive.token');
//     $pipedrive = new Pipedrive($token);
//     try
//     {
//         $data = $pipedrive->deals->find(1426)->getData();
//     }
//     catch(Exception $e)
//     {
//         $data = null;
//     }

//     Log::channel("marketplace")->info("aaaa", [
//         "data" => $data,
//     ]);

//     dd($data);


//     // // // $label     = [];
//     // // // foreach ($data as $d)
//     // // //     if ($d->id == 12463 && $d->key == "label")
//     // // //         $labels = $d->options;
//     // // // foreach ($labels as &$label)
//     // // // {
//     // // //     $label = [
//     // // //         "value" => $label->id,
//     // // //         "label" => $label->label
//     // // //     ];
//     // // // }
//     // // // // array_unshift($labels, [
//     // // //     // "value" => 0,
//     // // //     // "label" => "Sem Etiqueta"
//     // // // // ]);
//     // // // $data = [
//     // // //     "properties" => [
//     // // //         "list" => [
//     // // //             [
//     // // //                 "value" => 0,
//     // // //                 "label" => "Sem Etiqueta"
//     // // //             ],
//     // // //             ...$labels
//     // // //         ],
//     // // //     ]
//     // // // ];


//     // $factory    = Mautic::getFactory();
//     // $consumer   = MauticConsumer::whereNotNull('id')->orderBy('created_at', 'desc')->first();

//     // $expired    = $factory->checkExpirationTime($consumer->expires);

//     // $consumer   = ($expired) ? $factory->refreshToken($consumer->refresh_token) : $consumer;

//     // $settings   = config('mautic.connections.main');
//     // $settings['accessToken'] = $consumer->access_token;

//     // $initAuth   = new ApiAuth();
//     // $auth       = $initAuth->newAuth($settings);
//     // $apiUrl     = $settings['baseUrl'];
//     // $api        = new MauticApi();
//     // $contactApi = $api->newApi("contactFields", $auth, $apiUrl);

//     // $res = $contactApi->getList("", 0, 200)["fields"];

//     // $data = [];
//     // foreach ($res as $r)
//     // {
//     //     if ( $r["createdByUser"] == "Mateus Costa")
//     //         $data[] = $r;
//     // }

//     // // dd($data);

//     // $test = [
//     //     "isPublished" => true,
//     //     "label"       => "Funil Etiqueta",
//     //     "alias"       => "pipeline_etiqueta",
//     //     "type"        => "select",
//     //     "group"       => "core",
//     //     "order"       => 4,
//     //     "object"      => "lead",
//     //     "defaultValue" => "0",
//     //     "isRequired" => false,
//     //     "isPubliclyUpdatable" => false,
//     //     "isUniqueIdentifier" => false,
//     //     "properties" => [
//     //         "list" => [
//     //             [
//     //                 "label" => "Sem Etiqueta",
//     //                 "value" => 0
//     //             ]
//     //         ]
//     //     ],
//     // ];

//     // $field = $contactApi->create($test);

//     // dd($field);
// });

// // Route::any('/teste', [App\Http\Controllers\HomeController::class, 'teste'])->name('teste3');
// // Route::any('/assas', [App\Http\Controllers\HomeController::class, 'teste'])->name('teste');
