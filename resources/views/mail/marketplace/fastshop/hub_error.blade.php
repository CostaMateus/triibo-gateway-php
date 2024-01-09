<style> .code { font-family: "Courier New", "monospace", "monospace"; } </style>

@component('mail::message')
# {{ $title }}

@php
    echo "<pre class='code' >";
    echo "<b>CEP</b>: " . $data[ "cep" ] . "<br>";
    echo "<b>SKU</b>: " . $data[ "sku" ] . "<br>";
    echo "<b>HUB</b>: " . $data[ "hub" ] . "<br>";
    echo "<b></pre>";
@endphp

<br>

Saudações,<br>
Integração Triibo Fast Shop
@endcomponent
