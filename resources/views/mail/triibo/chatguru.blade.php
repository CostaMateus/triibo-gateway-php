<style> .code { font-family: "Courier New", "monospace", "monospace"; } </style>

@component('mail::message')
# {{ $title }}

@php
    echo "<pre class='code' >";
        print_r($params);
    echo "</pre>";
@endphp

<br>

Saudações,<br>
Integração Triibo ChatGuru
@endcomponent
