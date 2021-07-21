@component('mail::message')
# {{ $title }}

{{ $class . " ID: " . $params["meta"]["id"] }}

Parametros enviados:
@php
    echo "<pre>";
        print_r($params);
    echo "</pre>";
@endphp

<br>

Stacktrace:
{{ $stacktrace }}

<br>

Saudações,<br>
Integração Triibo Pipedrive-Mautic
@endcomponent
