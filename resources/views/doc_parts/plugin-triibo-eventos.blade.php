
    <div class="accordion-item" >
        <p class="accordion-header h2" id="headingPluginEventos" >
            <button class="accordion-button" type="button"
                data-bs-toggle="collapse" data-bs-target="#collapsePluginEventos"
                aria-expanded="true" aria-controls="collapsePluginEventos" >
                Plugin MKT - Triibo Eventos
                <span class="text-danger fw-bold ps-1" >(desatualizado)</span>
            </button>
        </p>
        <div id="collapsePluginEventos" class="accordion-collapse collapse" aria-labelledby="headingPluginEventos" data-bs-parent="#accordionTriibo" >
            <div class="accordion-body" >
                <div id="pluginEventos" >
                    <h3 class="text-center" >Triibo Eventos</h3>
                    <p class="mb-0" >
                        Dispara eventos para a Triibo dada certas condições.
                        <br>
                        Para que os eventos sejam acionados, o usuário precisa ter conta Triibo vinculada
                        <br><br>

                        Eventos:
                        <br>
                        - compras de produto normal, com status <var>concluído</var>, aciona o evento
                            '<strong>Compra_Marketplace</strong>';
                        <br>
                        - compras de assinatura, com status <var>processando</var>, aciona o evento
                            '<strong>Compra_Marketplace</strong>'
                        e um dos três eventos de assinatura:
                            '<strong>Assinatura_Triibo_Vip</strong>',
                            '<strong>Assinatura_Triibo_Vip_Plano_2</strong>',
                            '<strong>Assinatura_Triibo_Vip_Plano_3</strong>';
                        <br>
                        - renovação de assinatura, aciona o respectivo evento de assinatura:
                            '<strong>Assinatura_Triibo_Vip</strong>',
                            '<strong>Assinatura_Triibo_Vip_Plano_2</strong>',
                            '<strong>Assinatura_Triibo_Vip_Plano_3</strong>';
                        <br>

                        <br>
                        Depende dos plugins:<br>
                        - Triibo API Services;<br>
                        - WooCommerce;<br>
                        - WooCommerce Subscription;
                    </p>
                </div>
            </div>
        </div>
    </div>
