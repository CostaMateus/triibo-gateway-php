<style> .code { font-family: "Courier New", "monospace", "monospace"; } </style>

@component('mail::message')
# {{ $title }}

@php
    echo "<pre class='code' >";
    echo "<b>Pedido</b>: #"     . $data[ "order_id"     ] . "<br>";
    echo "<b>Sub-pedido</b>: #" . $data[ "sub_order_id" ] . "<br>";
    echo "<b>ID cliente</b>: "  . $data[ "customer_id"  ] . "<br>";
    echo "<b>Nome</b>: "        . $data[ "name"         ] . "<br>";
    echo "<b>E-mail</b>: "      . $data[ "email"        ] . "<br>";
    echo "<b>Telefone</b>: "    . $data[ "phone"        ] . "<br>";
    echo "<b></pre>";
@endphp

<br>

Saudações,<br>
Integração Triibo Fast Shop
@endcomponent
