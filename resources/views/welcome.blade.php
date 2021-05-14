<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Triibo') }}</title>
        <!-- Styles -->
        <style>
            body{font-family:Nunito,sans-serif;margin:0}*,:after,:before{box-sizing:border-box}.bg-triibo{--bg-opacity:1;background-color:#6e3296;background-color:rgba(110,50,150,var(--bg-opacity))}.flex{display:flex}.items-center{align-items:center}.justify-center{justify-content:center}.h-100{min-height:100vh}.triibo-logo{width:300px}
        </style>
    </head>
    <body>
        <div class="flex justify-center bg-triibo items-center h-100">
            <img class="triibo-logo" src="{{ asset('images/logo/logo-triibo.png')}}" alt="Triibo">
        </div>
    </body>
</html>
