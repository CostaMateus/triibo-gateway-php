<!doctype html>
<html lang="{{ str_replace( search: '_', replace: '-', subject: app()->getLocale() ) }}" >
    <head>
        <meta charset="utf-8" >
        <meta http-equiv="X-UA-Compatible" content="IE=edge" >
        <meta name="viewport" content="width=device-width, initial-scale=1" >
        <meta name="csrf-token" content="{{ csrf_token() }}" >

        @if ( auth()->check() )
            <meta name="robots" content="noindex" />
        @endif

        {{-- Title --}}
        <title>{{ config( key: "app.name", default: "Triibo" ) }}</title>

        {{-- CSS/JS --}}
        @vite( entrypoints: [
            "resources/sass/app.scss",
            "resources/css/app.css",
            "resources/js/app.js"
        ] )

        @yield( section: "css" )
    </head>
    <body class="bg-dots" >
        <div id="app" >
            <main>
                @yield( section: "content" )
            </main>
        </div>

        @yield( section: "js" )
    </body>
</html>
