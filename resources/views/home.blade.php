@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">

        <div class="col-md-8">
            <h2 class="text-left">Documentação</h2>
        </div>
        <div class="col-md-8">

            <div class="accordion accordion-flush " id="accordionTriibo">

                <div class="accordion-item">
                    <p class="accordion-header h2" id="headingCofry">
                        <button class="accordion-button" type="button"
                            data-bs-toggle="collapse" data-bs-target="#collapseCofry"
                            aria-expanded="true" aria-controls="collapseCofry" >
                            Integração Confry
                        </button>
                    </p>
                    <div id="collapseCofry" class="accordion-collapse collapse" aria-labelledby="headingCofry" data-bs-parent="#accordionTriibo" >
                        <div class="accordion-body">
                            <div id="cofry">
                                <h3 class="text-center" >Integração Confry</h3>
                                <p class="mb-0" >Ações automáticas</p>
                                <ul class="mb-0" >
                                    <li>
                                        Importa imagens
                                        <span class="text-muted h7" >(todos os dias às 02h)</span>
                                    </li>
                                    <li>
                                        Importa lojas e promoções
                                        <span class="text-muted h7" >(duas vezes no dia, às 02h e às 14h)</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <p class="accordion-header h2" id="headingChatGuru">
                        <button class="accordion-button" type="button"
                            data-bs-toggle="collapse" data-bs-target="#collapseChatGuru"
                            aria-expanded="true" aria-controls="collapseChatGuru" >
                            Integração ChatGuru
                        </button>
                    </p>
                    <div id="collapseChatGuru" class="accordion-collapse collapse" aria-labelledby="headingChatGuru" data-bs-parent="#accordionTriibo" >
                        <div class="accordion-body">
                            <div id="chatguru">
                                <h3 class="text-center" >Integração ChatGuru</h3>
                                <p class="mb-0" >
                                    Os dados do formulário (<a href="https://triibo.com.br/cadastre-seu-negocio" class="text-muted h6" target="_blank" >triibo.com.br/cadastre-seu-negocio)</a>
                                    são enviados pelo plugin "Triibo Elementor Form Webhook". <br>
                                    As informações são tratadas e enviadas para o ChatGuru. <br>
                                    <br>
                                    Caso haja falha ao criar um chat no ChatGuru, um e-mail, com os dados do formulário, será enviado para atendimento@triibo.com.br
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <p class="accordion-header h2" id="headingMauticPipe">
                        <button class="accordion-button" type="button"
                            data-bs-toggle="collapse" data-bs-target="#collapseMauticPipe"
                            aria-expanded="true" aria-controls="collapseMauticPipe" >
                            Integração Mauti-Pipe
                        </button>
                    </p>
                    <div id="collapseMauticPipe" class="accordion-collapse collapse" aria-labelledby="headingMauticPipe" data-bs-parent="#accordionTriibo" >
                        <div class="accordion-body">
                            <div id="marketplace">
                                <h3 class="text-center mb-3" >Integração Mautic-Pipedrive</h3>
                                <p class="text-center d-none" >Os dados fluem do Pipedrive para Comunica.Mautic apenas.</p>
                                <p class="mb-0" >Ações automáticas</p>
                                <ul class="" >
                                    <li>
                                        Atualiza os campos:
                                        <span class="text-muted h7" >(todos os dias às 05h)</span>
                                        <ul>
                                            <li>Pipedrive Funil</li>
                                            <li>Pipedrive Funil Status</li>
                                            <li>Pipedrive Funil Etiqueta</li>
                                        </ul>
                                    </li>
                                </ul>

                                <p class="mt-2 mb-0" >Sincronização em tempo real via webhook</p>
                                <ul>
                                    <li>Alterações em massa no Pipedrive, não gera alterações no Mautic</li>
                                    <li>Dados sincronizados:
                                        <ul>
                                            <li>
                                                Organização
                                                <ul>
                                                    <li>ID</li>
                                                    <li>Nome</li>
                                                    <li>E-mail</li>
                                                    <li>Telefone</li>
                                                    <li>Fonte do Lead</li>
                                                    <li>Vendedor responsável</li>
                                                </ul>
                                            </li>
                                            <li>
                                                Pessoa
                                                <span class="text-muted h7" >não sincronizada</span>
                                                {{-- <ul>
                                                    <li>ID Pessoa</li>
                                                    <li>Nome</li>
                                                    <li>Sobrenome</li>
                                                </ul> --}}
                                            </li>
                                            <li>
                                                Negócio
                                                <ul>
                                                    <li>ID da organização</li>
                                                    <li>Nome da organização</li>
                                                    <li>
                                                        Funil/Etapa
                                                        <span class="text-muted h7" >(Sem Funil ou nome da etapa)</span>
                                                    </li>
                                                    <li>
                                                        Status
                                                        <span class="text-muted h7" >(Sem Status, Aberto, Ganho, Perdido ou Excluído)</span>
                                                    </li>
                                                    <li>
                                                        Etiqueta
                                                        <span class="text-muted h7" >(Sem Etiqueta, Ativo, Cancelado, em Espera ou em Degustação)</span>
                                                    </li>
                                                </ul>
                                            </li>
                                            <li>
                                                Funil
                                                <ul>
                                                    <li>
                                                        Etapa
                                                        <span class="text-muted h7" >(ID e Nome)</span>
                                                    </li>
                                                    <li>Status
                                                        <span class="text-muted h7" >(ID e Nome)</span>
                                                    </li>
                                                    <li>Etiqueta
                                                        <span class="text-muted h7" >(ID e Nome)</span>
                                                    </li>
                                                </ul>
                                            </li>
                                        </ul>
                                    </li>
                                </ul>

                                <p class="text-center mb-0" >Correspondência dos campos sincronizados.</p>
                                <table class="table table-sm col-12 col-md-10 mx-auto" >
                                    <thead>
                                        <tr>
                                            <th class="text-end w-50"   colspan="2" scope="col">Mautic</th>
                                            <th class="text-start w-50" colspan="2" scope="col">Pipedrive</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {{-- <tr>
                                            <td class="text-muted h7 text-start w-10" >(Contato)</td>
                                            <td class="text-end w-40"                >ID</td>
                                            <td class="text-start w-40"               >Mautic Id</td>
                                            <td class="text-muted h7 text-end w-10" >(Pessoa)</td>
                                        </tr> --}}
                                        <tr>
                                            <td class="text-muted h7 text-start w-10" >(Contato)</td>
                                            <td class="text-end w-40"                 >ID</td>
                                            <td class="text-start w-40"               >Mautic Id</td>
                                            <td class="text-muted h7 text-end w-10"   >(Organização)</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted h7 text-start w-10" >(Contato)</td>
                                            <td class="text-end w-40"                 >Pipedrive ID Organization</td>
                                            <td class="text-start w-40"               >ID</td>
                                            <td class="text-muted h7 text-end w-10"   >(Organização)</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted h7 text-start w-10" >(Contato)</td>
                                            <td class="text-end w-40"                 >Nome</td>
                                            <td class="text-start w-40"               > - </td>
                                            <td class="text-muted h7 text-end w-10"   >(não usado)</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted h7 text-start w-10" >(Contato)</td>
                                            <td class="text-end w-40"                 >Sobrenome</td>
                                            <td class="text-start w-40"               > - </td>
                                            <td class="text-muted h7 text-end w-10"   >(não usado)</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted h7 text-start w-10" >(Contato)</td>
                                            <td class="text-end w-40"                 >Email</td>
                                            <td class="text-start w-40"               >E-mail</td>
                                            <td class="text-muted h7 text-end w-10"   >(Organização)</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted h7 text-start w-10" >(Contato)</td>
                                            <td class="text-end w-40"                 >Pipedrive Nome da Empresa</td>
                                            <td class="text-start w-40"               >Nome da organização</td>
                                            <td class="text-muted h7 text-end w-10"   >(Organização)</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted h7 text-start w-10" >(Contato)</td>
                                            <td class="text-end w-40"                 >Pipedrive Telefone</td>
                                            <td class="text-start w-40"               >Telefone</td>
                                            <td class="text-muted h7 text-end w-10"   >(Organização)</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted h7 text-start w-10" >(Contato)</td>
                                            <td class="text-end w-40"                 >Pipedrive Funil</td>
                                            <td class="text-start w-40"               >Etapa do funil</td>
                                            <td class="text-muted h7 text-end w-10"   >(Negócio)</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted h7 text-start w-10" >(Contato)</td>
                                            <td class="text-end w-40"                 >Pipedrive Funil Status</td>
                                            <td class="text-start w-40"               >Status do negócio</td>
                                            <td class="text-muted h7 text-end w-10"   >(Negócio)</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted h7 text-start w-10" >(Contato)</td>
                                            <td class="text-end w-40"                 >Pipedrive Funil Etiqueta</td>
                                            <td class="text-start w-40"               >Etiqueta do negócio</td>
                                            <td class="text-muted h7 text-end w-10"   >(Negócio)</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted h7 text-start w-10" >(Contato)</td>
                                            <td class="text-end w-40"                 >Pipedrive Fonte do Lead</td>
                                            <td class="text-start w-40"               >Fonte do Lead</td>
                                            <td class="text-muted h7 text-end w-10"   >(Organização)</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted h7 text-start w-10" >(Contato)</td>
                                            <td class="text-end w-40"                 >Pipedrive Vendedor Resp.</td>
                                            <td class="text-start w-40"               >Etiqueta do negócio</td>
                                            <td class="text-muted h7 text-end w-10"   >(Negócio)</td>
                                        </tr>
                                        {{-- <tr>
                                            <td class="text-muted h7 text-start w-10" >(Contato)</td>
                                            <td class="text-end w-40"                 >Pipedrive ID Person</td>
                                            <td class="text-start w-40"               >ID</td>
                                            <td class="text-muted h7 text-end w-10"   >(Pessoa)</td>
                                        </tr> --}}
                                    </tbody>
                                </table>

                                <p class="mt-2 mb-0">Links úteis</p>
                                <ul>
                                    <li>
                                        Sincroniza IDs <span class="text-muted h7" >(apenas se encontrar o e-mail da organização no Mautic)</span>
                                        <br>
                                        <a href="{{ route("sync.ids") }}" target="_blank" >Sincronizar agora!</a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

        </div>

    </div>
</div>
@endsection
