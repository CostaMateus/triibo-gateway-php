@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">

        <div class="col-md-8">
            <h2 class="text-left">Documentação</h2>
        </div>
        <div class="col-md-8">

            <div class="accordion" id="accordionTriibo">
                <div class="card">
                    <div class="card-header" id="headingOne">
                        <h2 class="mb-0">
                            <button class="btn btn-link btn-block text-left text-triibo"
                                type="button" data-toggle="collapse" data-target="#collapseCofry"
                                aria-expanded="true" aria-controls="collapseCofry" >
                                Integração Confry
                            </button>
                        </h2>
                    </div>

                    <div id="collapseCofry" class="collapse" aria-labelledby="headingOne" data-parent="#accordionTriibo">
                        <div class="card-body">
                            <div id="cofry">
                                <h2 class="text-center" >Integração Confry</h2>
                                <p class="mb-0" >Ações automáticas</p>
                                <ul class="mb-0" >
                                    <li>
                                        Importa imagens
                                        <span class="text-muted h7" >(aos domingos, terças e sextas às 01h30)</span>
                                    </li>
                                    <li>
                                        Importa lojas
                                        <span class="text-muted h7" >(todos os dias às 03h)</span>
                                    </li>
                                    <li>
                                        Importa promoções
                                        <span class="text-muted h7" >(todos os dias às 02h)</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header" id="headingTwo">
                        <h2 class="mb-0">
                            <button class="btn btn-link btn-block text-left text-triibo collapsed" type="button"
                                data-toggle="collapse" data-target="#collapseMauticPipe"
                                aria-expanded="false" aria-controls="collapseMauticPipe" >
                                Integração Mautic-Pipedrive
                            </button>
                        </h2>
                    </div>
                    <div id="collapseMauticPipe" class="collapse" aria-labelledby="headingTwo" data-parent="#accordionTriibo">
                        <div class="card-body">

                            <div id="marketplace">
                                <h2 class="text-center" >Integração Mautic-Pipedrive</h2>
                                <p class="text-center" >Os dados fluem do Pipedrive para Comunica.Mautic apenas.</p>
                                <p class="mb-0" >Ações automáticas</p>
                                <ul class="" >
                                    <li>
                                        Importa o nome dos estágios dos funis
                                        <span class="text-muted h7" >(toda segunda às 05h)</span>
                                    </li>
                                </ul>
                                <p class="mt-2 mb-0" >Sincronização em tempo real</p>
                                <ul>
                                    <li>Alterações em massa no Pipedrive, não gera alterações no Mautic</li>
                                    <li>Dados sincronizados:
                                        <ul>
                                            <li>Organização - não é sincronizado
                                                {{-- <ul>
                                                    <li>ID</li>
                                                    <li>Nome</li>
                                                    <li>Endereço</li>
                                                    <li>E-mail</li>
                                                    <li>Telefone</li>
                                                </ul> --}}
                                            </li>
                                            <li>Pessoa
                                                <ul>
                                                    <li>ID Pessoa</li>
                                                    <li>Nome</li>
                                                    <li>Sobrenome</li>
                                                    <li>E-mail</li>
                                                    <li>Telefone</li>
                                                </ul>
                                            </li>
                                            <li>Negócio
                                                <ul>
                                                    <li>ID Pessoa</li>
                                                    <li>Nome do estágio do funil</li>
                                                    <li>
                                                        Status
                                                        <span class="text-muted h7" >(aberto, ganho, perdido)</span>
                                                    </li>
                                                </ul>
                                            </li>
                                            <li>Estágio
                                                <ul>
                                                    <li>ID Estágio</li>
                                                    <li>Nome do estágio</li>
                                                </ul>
                                            </li>
                                        </ul>
                                    </li>
                                </ul>

                                <p class="mb-0" >
                                    Caso seja feita a alteração do nome de dois ou mais estágios de uma vez, apenas o último será sincronizado automaticamente.
                                    Após a alteraçao faz-se necessário acesso a seguinte URL: <br>
                                </p>
                                <ul>
                                    <li>
                                        <code>{{ env('APP_URL') . 'import-stages' }}</code>
                                    </li>
                                </ul>

                                <p class="text-center mb-0" >Correspondência dos campos sincronizados.</p>
                                <table class="table table-sm col-12 col-md-10 mx-auto" >
                                    <thead>
                                        <tr>
                                            <th class="text-right w-50" colspan="2" scope="col">Mautic</th>
                                            <th class="text-left  w-50" colspan="2" scope="col">Pipedrive</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td class="text-muted h7 text-left  w-20" >(Contato)</td>
                                            <td class="text-right w-30"               >ID</td>
                                            <td class="text-left  w-30"               >Mautic Id</td>
                                            <td class="text-muted h7 text-right w-20" >(Pessoa)</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted h7 text-left  w-20" >(Contato)</td>
                                            <td class="text-right w-30"               >Nome</td>
                                            <td class="text-left  w-30"               >Primeiro Nome</td>
                                            <td class="text-muted h7 text-right w-20" >(Pessoa)</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted h7 text-left  w-20" >(Contato)</td>
                                            <td class="text-right w-30"               >Sobrenome</td>
                                            <td class="text-left  w-30"               >Sobrenome</td>
                                            <td class="text-muted h7 text-right w-20" >(Pessoa)</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted h7 text-left  w-20" >(Contato)</td>
                                            <td class="text-right w-30"               >Email</td>
                                            <td class="text-left  w-30"               >E-mail</td>
                                            <td class="text-muted h7 text-right w-20" >(Pessoa)</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted h7 text-left  w-20" >(Contato)</td>
                                            <td class="text-right w-30"               >Phone</td>
                                            <td class="text-left  w-30"               >Telefone</td>
                                            <td class="text-muted h7 text-right w-20" >(Pessoa)</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted h7 text-left  w-20" >(Contato)</td>
                                            <td class="text-right w-30"               >Funil</td>
                                            <td class="text-left  w-30"               >Nome do estágio do funil</td>
                                            <td class="text-muted h7 text-right w-20" >(Negócio)</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted h7 text-left  w-20" >(Contato)</td>
                                            <td class="text-right w-30"               >Funil Status</td>
                                            <td class="text-left  w-30"               >Status do negócio</td>
                                            <td class="text-muted h7 text-right w-20" >(Negócio)</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted h7 text-left  w-20" >(Contato)</td>
                                            <td class="text-right w-30"               >Pipedrive ID Person</td>
                                            <td class="text-left  w-30"               >ID</td>
                                            <td class="text-muted h7 text-right w-20" >(Pessoa)</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div>
</div>
@endsection
