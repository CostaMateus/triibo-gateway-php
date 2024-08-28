<?php

namespace App\Http\Controllers\Vtex;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ProductController extends Controller
{
    /**
     * Handles the creation of a new SKU based on the request data.
     *
     * @see     https://developers.vtex.com/docs/guides/external-marketplace-integration-new-products
     *
     * @param   Request     $request
     * @return  void
     */
    public function newSku( Request $request )
    {
        // TODO tratar dados

        /**
         * Validar os dados recebidos
         * Verificar 'isActive' se é 'true' ou 'false'
         * Se 'true' consulta FulfillmentService->simulate() para obter preço e estoque
         */
    }
}
