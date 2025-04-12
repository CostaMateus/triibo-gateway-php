<?php

declare(strict_types = 1);

return [

    "project_id" => ( env( "TRIIBO_ENV" ) === "production" ) ? env( "FIRESTORE_PROJECT_ID_PROD" ) : env( "FIRESTORE_PROJECT_ID_HML" ),
    "api_key" => ( env( "TRIIBO_ENV" ) === "production" ) ? env( "FIRESTORE_API_KEY_PROD" ) : env( "FIRESTORE_API_KEY_HML" ),
    "database" => [ "database" => "(default)" ],

];
