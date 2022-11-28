<style> .code { font-family: "Courier New", "monospace", "monospace"; } </style>

@component('mail::message')
# {{ $title }}

{{ "Metodo: $method" }}
<br>
{{ "Catch:  $catch"  }}

<b>Stacktrace</b>:
<br>
<div class="code" >
    {{ $stacktrace }}
</div>

<br>

Saudações,<br>
Integração Cofry
@endcomponent
