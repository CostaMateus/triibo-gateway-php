<style> .code { font-family: "Courier New", "monospace", "monospace"; } </style>

@component('mail::message')
# {{ $title }}

Os seguintes campos não estão configurados corretamente:

@foreach ($params as $key => $item)
@if ($item)
<b>{{ $key }}</b><br>
@endif
@endforeach

Saudações,<br>
Integração Triibo ChatGuru
@endcomponent
