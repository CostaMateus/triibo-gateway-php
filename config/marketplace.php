<?php

declare(strict_types = 1);

return [

    "env" => env( key : "MKT_ENV", default : "local" ),

    "url" => ( env( key : "MKT_ENV" ) === "local" ) ? "https://dev-marketplace.triibo.com.br" : "https://marketplace.triibo.com.br",

];
