<?php

return [

    /**
     * Plugin key and token.
     */
    "plugins"            => [
        "key"   => ( env( key: "MKT_ENV" ) == "local" ) ? env( key: "WOO_PLG_HML_KEY"   ) : env( key: "WOO_PLG_PRD_KEY"   ),
        "token" => ( env( key: "MKT_ENV" ) == "local" ) ? env( key: "WOO_PLG_HML_TOKEN" ) : env( key: "WOO_PLG_PRD_TOKEN" ),
    ],

    /**
     * Store URL eg: http://example.com
     */
    "store_url"          => ( env( key: "MKT_ENV" ) == "local" ) ? env( key: "MKT_STORE_HML_URL" ) : env( key: "MKT_STORE_PRD_URL" ),

    /**
     * Consumer Key.
     */
    "consumer_key"       => ( env( key: "MKT_ENV" ) == "local" ) ? env( key: "WOO_HML_KEY"       ) : env( key: "WOO_PRD_KEY"       ),

    /**
     * Consumer Secret.
     */
    "consumer_secret"    => ( env( key: "MKT_ENV" ) == "local" ) ? env( key: "WOO_HML_SECRET"    ) : env( key: "WOO_PRDSECRET"     ),

    /**
     * SSL support
     */
    "verify_ssl"         => env( key: "WOO_VERIFY_SSL",            default: false ),

    /**
     * Woocommerce API version
     */
    "api_version"        => env( key: "WOO_API_VERSION",           default: "v3" ),

    /**
     * Enable WP API Integration
     */
    "wp_api"             => env( key: "WP_API_INTEGRATION",        default: true ),

    /**
     * Force Basic Authentication as query string
     */
    "query_string_auth"  => env( key: "WOO_WP_QUERY_AUTH",         default: true ),

    /**
     * Default WP timeout
     */
    "timeout"            => env( key: "WOO_WP_TIMEOUT",            default: 300 ),

    /**
     * Total results header. Default value "X-WP-Total"
     */
    "header_total"       => env( key: "WOO_WP_HEADER_TOTAL",       default: "X-WP-Total" ),

    /**
     * Total pages header. Default value "X-WP-TotalPages"
     */
    "header_total_pages" => env( key: "WOO_WP_HEADER_TOTAL_PAGES", default: "X-WP-TotalPages" ),
];
