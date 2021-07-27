<style> .code { font-family: "Courier New", "monospace", "monospace"; } </style>

@component('mail::message')
# {{ $title }}

{{ $class . " ID: " . $params["meta"]["id"] }}

Parametros enviados:
@php
    echo "<pre class='code' >";
        print_r($params);
    echo "</pre>";
@endphp

<br>

<b>Stacktrace</b>:
<br>
<div class="code" >
    {{ $stacktrace }}
</div>

<br>

Saudações,<br>
Integração Triibo Pipedrive-Mautic
@endcomponent
