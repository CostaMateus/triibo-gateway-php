@extends('layouts.app')

@section('content')
<div class="container" >
    <div class="row justify-content-center" >

        <div class="col-md-8" >
            <h2 class="text-left" >Documentação</h2>
        </div>
        <div class="col-md-8" >

            <div class="accordion accordion-flush" id="accordionTriibo" >

                @include('doc_parts.chatguru')
                @include('doc_parts.awin')
                @include('doc_parts.cofry')
                @include('doc_parts.fastshop')
                @include('doc_parts.mautic')

            </div>

        </div>

    </div>
</div>
@endsection
