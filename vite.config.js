import { defineConfig } from "vite";
import laravel          from "laravel-vite-plugin";

export default defineConfig( {
    plugins: [
        laravel( {
            input: [
                "resources/sass/app.scss",
                "resources/css/app.css",
                "resources/js/app.js",
            ],
            refresh: true,
        } ),
        {
            name: "blade",
            handleHotUpdate( { file, server } )
            {
                if ( file.endsWith( ".blade.php" ) )
                {
                    server.ws.send( {
                        type: "full-reload",
                        path: "*",
                    } );
                }
            },
        },
    ],
    resolve: {
        alias: {
            jquery: "jquery/dist/jquery.js",
        },
    },
    optimizeDeps: {
        include: [
            "jquery",
            "jquery-mask-plugin"
        ],
    },
} );
