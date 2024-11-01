
    <div class="accordion-item">
        <p class="accordion-header h2" id="headingFastshop">
            <button class="accordion-button" type="button"
                data-bs-toggle="collapse" data-bs-target="#collapseFastshop"
                aria-expanded="true" aria-controls="collapseFastshop" >
                Integração Fast Shop
            </button>
        </p>
        <div id="collapseFastshop" class="accordion-collapse collapse" aria-labelledby="headingFastshop" data-bs-parent="#accordionTriibo" >
            <div class="accordion-body">
                <div id="fastshop">
                    <h3 class="text-center" >Integração Fast Shop</h3>
                    <p>
                        URL base:<br>
                        @if (config("app.env") == "local")
                            <span class="font-monospace" ><var>php-hml.triibo.com.br/api/marketplace</var></span>
                        @else
                            <span class="font-monospace" ><var>php.triibo.com.br/api/marketplace</var></span>
                        @endif
                    </p>

                    <hr>

                    <div>
                        <p class="my-4 h3" >Fast Shop</p>

                        <div id="changenotification" class="mb-4" >
                            <p class="mb-2 h4 text-decoration-underline">Notificação de catálogo</p>
                            <p class="mb-0" style="font-size: .75rem">
                                <a href="https://fastshop.gitbook.io/fastshop-api-seller/integracao/recursos/notificacao-de-catalogo" class="text-decoration-none link-info" target="_blank">Documentação Fast Shop</a>
                            </p>
                            <p class="mb-0" ><strong>POST</strong></p>
                            <p class="mb-0 font-monospace text-break" >
                                <mark>/changenotification/api/catalog_system/pvt/skuseller/changenotification/<var>{sellerId}</var>/<var>{sellerSkuId}</var></mark>
                            </p>
                            <p class="mb-0" >
                                Quando o <var>{sellerSkuId}</var> for criado ou alterado, a FS notificará o Marketplace através deste <var>endpoint</var>.
                                <br>
                                Será validado se o <var>{sellerSkuId}</var> está cadastrado no Marketplace.
                            </p>
                            <p class="mb-0" >
                                Caso sim, buscará na FS os dados atualizados do <var>{sellerSkuId}</var>.
                                <br>
                                Caso não, a FS chamará <a href="#skusuggestion" class="link-primary text-decoration-none" >Registro de catálogo</a>.
                            </p>
                        </div>

                        <div id="skusuggestion" class="mb-4" >
                            <p class="mb-2 h4 text-decoration-underline">Registro de catálogo</p>
                            <p class="mb-0" style="font-size: .75rem">
                                <a href="https://fastshop.gitbook.io/fastshop-api-seller/integracao/recursos/registro-de-catalogo" class="text-decoration-none link-info" target="_blank">Documentação Fast Shop</a>
                            </p>
                            <p class="mb-0" ><strong>PUT</strong></p>
                            <p class="mb-0 font-monospace text-break" >
                                <mark>/skusuggestion/suggestions/<var>{sellerId}</var>/<var>{sellerSkuId}</var></mark>
                            </p>
                            <p class="mb-0" >
                                A FS enviará um novo <var>{sellerSkuId}</var> para ser cadastrado no Marketplace.
                            </p>
                        </div>

                        <div id="invoiceNotification" class="mb-4" >
                            <p class="mb-2 h4 text-decoration-underline">Pedido Despachado</p>
                            <p class="mb-0" style="font-size: .75rem">
                                <a href="https://fastshop.gitbook.io/fastshop-api-seller/integracao/recursos/pedido-faturado-ou-despachado" class="text-decoration-none link-info" target="_blank">Documentação Fast Shop</a>
                            </p>
                            <p class="mb-0" ><strong>POST</strong></p>
                            <p class="mb-0 font-monospace text-break" >
                                <mark>/invoice/api/oms/pub/orders/<var>{orderId}</var>/invoice</mark>
                            </p>
                            <p class="mb-0" >
                                O <var>{orderId}</var> é o número do pedido no Marketplace.
                                <br>
                                Quando o <var>{orderId}</var> for despachado pela FS (entregue a transportadora), os dados de rastreamento serão atualizados utilizando este <var>endpoint</var>.
                            </p>
                        </div>

                        <div id="deliveredNotification" class="mb-4" >
                            <p class="mb-2 h4 text-decoration-underline">Pedido Entregue</p>
                            <p class="mb-0" style="font-size: .75rem">
                                <a href="https://fastshop.gitbook.io/fastshop-api-seller/integracao/recursos/pedido-entregue" class="text-decoration-none link-info" target="_blank">Documentação Fast Shop</a>
                            </p>
                            <p class="mb-0" ><strong>POST</strong></p>
                            <p class="mb-0 font-monospace text-break" >
                                <mark>/delivered/api/oms/pub/orders/<var>{orderId}</var>/delivered</mark>
                            </p>
                            <p class="mb-0" >
                                O <var>{orderId}</var> é o número do pedido no Marketplace.
                                <br>
                                Quando o <var>{orderId}</var> for entregue, a FS notificará o Marketplace utilizando este <var>endpoint</var>.
                            </p>
                        </div>

                        <div id="cancellationNotification" class="mb-4" >
                            <p class="mb-2 h4 text-decoration-underline">Pedido Cancelado pela FS</p>
                            <p class="mb-0" style="font-size: .75rem">
                                <a href="https://fastshop.gitbook.io/fastshop-api-seller/integracao/recursos/pedido-cancelado-pelo-seller" class="text-decoration-none link-info" target="_blank">Documentação Fast Shop</a>
                            </p>
                            <p class="mb-0" ><strong>POST</strong></p>
                            <p class="mb-0 font-monospace text-break" >
                                <mark>/cancel/api/oms/pub/orders/<var>{orderId}</var>/cancel</mark>
                            </p>
                            <p class="mb-0" >
                                O <var>{orderId}</var> é o número do pedido no Marketplace.
                                <br>
                                Caso a FS tenha problemas com o <var>{orderId}</var> e tenha que cancelar, notificará o Marketplace utilizando este <var>endpoint</var>.
                            </p>
                        </div>
                    </div>

                    <hr>

                    <div id="plugin" >
                        <p class="my-4 h3" >Marketplace Triibo (plugin #Triibo_Fast_Shop)</p>

                        <div id="updatesku" class="mb-4" >
                            <p class="mb-2 h4 text-decoration-underline">Atualização de preço e estoque</p>
                            <p class="mb-0" style="font-size: .75rem">
                                <a href="https://fastshop.gitbook.io/fastshop-api-seller/integracao/recursos/atualizacao-de-preco-estoque" class="text-decoration-none link-info" target="_blank">Documentação Fast Shop</a>
                            </p>
                            <p class="mb-0" ><strong>POST</strong></p>
                            <p class="mb-0 font-monospace text-break" >
                                <mark>/wc/updatesku/<var>{sku}</var></mark>
                            </p>
                            <p class="mb-0" >
                                Consulta o <var>{sku}</var> na FS e atualiza preço e estoque.
                            </p>
                        </div>

                        <div id="freightcalculation" class="mb-4" >
                            <p class="mb-2 h4 text-decoration-underline">Cálculo de frete</p>
                            <p class="mb-0" style="font-size: .75rem">
                                <a href="https://fastshop.gitbook.io/fastshop-api-seller/integracao/recursos/calculo-de-frete" class="text-decoration-none link-info" target="_blank">Documentação Fast Shop</a>
                            </p>
                            <p class="mb-0" ><strong>POST</strong></p>
                            <p class="mb-0 font-monospace text-break" >
                                <mark>/wc/freightcalculation</mark>
                            </p>
                            <p class="mb-0" >
                                Consulta o frete na FS, para determinado par de SKU e CEP informados.
                            </p>
                        </div>

                        <div id="ordercreation" class="mb-4" >
                            <p class="mb-2 h4 text-decoration-underline">Criação de pedido</p>
                            <p class="mb-0" style="font-size: .75rem">
                                <a href="https://fastshop.gitbook.io/fastshop-api-seller/integracao/recursos/criacao-de-pedido" class="text-decoration-none link-info" target="_blank">Documentação Fast Shop</a>
                            </p>
                            <p class="mb-0" ><strong>POST</strong></p>
                            <p class="mb-0 font-monospace text-break" >
                                <mark>/wc/ordercreation/<var>{orderId}</var></mark>
                            </p>
                            <p class="mb-0" >
                                O <var>{orderId}</var> é o número do pedido no Marketplace.
                                <br>
                                Quando uma compra é finalizada no Marketplace, informará a FS do <var>{orderId}</var> realizado.
                            </p>
                        </div>

                        <div id="orderapproval" class="mb-4" >
                            <p class="mb-2 h4 text-decoration-underline">Pedido Aprovado e Pago</p>
                            <p class="mb-0" style="font-size: .75rem">
                                <a href="https://fastshop.gitbook.io/fastshop-api-seller/integracao/recursos/pedido-aprovado-e-pago" class="text-decoration-none link-info" target="_blank">Documentação Fast Shop</a>
                            </p>
                            <p class="mb-0" ><strong>POST</strong></p>
                            <p class="mb-0 font-monospace text-break" >
                                <mark>/wc/orderapproval/<var>{orderId}</var></mark>
                            </p>
                            <p class="mb-0" >
                                O <var>{orderId}</var> é o número do pedido no Marketplace.
                                <br>
                                Quando o pagamento do <var>{orderId}</var> for aprovado, informará a FS para prosseguir para a entrega.
                            </p>
                        </div>

                        <div id="ordercancelation" class="mb-4" >
                            <p class="mb-2 h4 text-decoration-underline">Pedido Cancelado pelo Marketplace</p>
                            <p class="mb-0" style="font-size: .75rem">
                                <a href="https://fastshop.gitbook.io/fastshop-api-seller/integracao/recursos/pedido-cancelado-pelo-marketplace" class="text-decoration-none link-info" target="_blank">Documentação Fast Shop</a>
                            </p>
                            <p class="mb-0" ><strong>POST</strong></p>
                            <p class="mb-0 font-monospace text-break" >
                                <mark>/wc/ordercancelation/<var>{orderId}</var></mark>
                            </p>
                            <p class="mb-0" >
                                O <var>{orderId}</var> é o número do pedido no Marketplace.
                                <br>
                                Quando o <var>{orderId}</var> for cancelado no Markerplace, informará a FS para prosseguir com o cancelamento.
                            </p>
                        </div>
                    </div>

                    <hr>

                    <div id="integration" >
                        <p class="my-4 h3" >Integração FS</p>
                        <p class="mb-0" >Ações automáticas</p>
                        <ul class="mb-0" >
                            <li>
                                Importa, do MKT para a Integração, todas as categorias de produtos
                                <span class="text-muted h7" >(diariamente às 23h59)</span>
                                <br>
                                <p class="mb-1" >Processo necessário para agilizar a criação/atualização dos produtos</p>
                                <p><mark >app\Console\Commands\Marketplace\GetCategories.php</mark></p>
                            </li>
                            <li>
                                Atualiza toda base de SKU da FastShop
                                <span class="text-muted h7" >(diariamente às 00h01)</span>
                                <br>
                                <p class="mb-0 fw-bold" >Atualização da base local</p>
                                <p class="mb-1" >
                                    1) Todos SKUs que seguirem os critérios abaixo, são consultados, de uma só vez, na FastShop.<br>
                                    - <var>status</var> igual a <var>active</var> ou <var>null</var> e <var>updated_at</var> igual ou inferior a 15 dias (quando maior, recebe <var>status</var> igual a <var>inactive</var> e <var>inactivated_at</var> com a data atual)<br>
                                    - <var>status</var> igual a <var>inactive</var> e <var>inactivated_at</var> maior que 15 dias
                                </p>
                                <p class="mb-1" >
                                    2) Os dados locais são comparados com resultados da consulta (<var>response</var>).<br>
                                    - se o <var>SKU</var> local não for encontrado no <var>response</var>, o mesmo se torna <var>inactive</var><br>
                                    - caso <var>stock</var> for 0 (zero), <var>regular_price</var> e <var>sale_price</var> não tiverem alteração, o <var>updated_at</var> for maior que 15 dias ou o SKU tiver <var>status</var> igual a <var>inactive</var>, o <var>inactivated_at</var> é atualizado para data atual<br>
                                    - caso contrário, <var>updated_at</var> recebe data atual e <var>status</var> recebe <var>active</var>
                                </p>

                                <p class="mb-0 fw-bold" >Atualização do MKT</p>
                                <p class="mb-1" >Dos dados SKUs selecionados no ponto 1 da atualização local, são enviados em grupos de 100 para atualização no MKT.</p>

                                <p><mark >app\Console\Commands\Marketplace\GetStockPriceFastShop.php</mark></p>
                            </li>
                            <li>
                                Exclui SKUs duplicados da FastShop
                                <span class="text-muted h7" >(diariamente às 01h03)</span>
                                <p><mark >app\Console\Commands\Marketplace\DeleteDuplicateSku.php</mark></p>
                            </li>
                            <li>
                                Torna privado os SKUs que estão sem imagem
                                <span class="text-muted h7" >(diariamente às 02h05)</span>
                                <p><mark >app\Console\Commands\Marketplace\ProductPrivateWithoutImg.php</mark></p>
                            </li>
                            <li>
                                Exclui produtos sem SKUs da FastShop
                                <span class="text-muted h7" >(diariamente às 05h)</span>
                                <p><mark >app\Console\Commands\Marketplace\DeleteProductWithoutSku.php</mark></p>
                            </li>
                        </ul>
                    </div>

                </div>
            </div>
        </div>
    </div>
