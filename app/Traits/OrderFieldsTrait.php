<?php

namespace App\Traits;

trait OrderFieldsTrait
{
    /**
     * Undocumented function
     *
     * @param   array $data
     * @param   array $order
     * @return  void
     */
    private function reorganizeFields( array $data, array $order ) : array
    {
        $result = [];

        foreach ( $order as $field )
            if ( isset( $data[ $field ] ) )
                $result[ $field ] = $data[ $field ];

        return $result;
    }

    /**
     * Undocumented function
     *
     * @return  array
     */
    private function getBillingFieldsOrder() : array
    {
        return [
            "company",
            "email",
            "phone",
            "cnpj",
            "ie",
            "postcode",
            "address_1",
            "number",
            "address_2",
            "neighborhood",
            "city",
            "state",
            "country",
            "first_name",
            "last_name",
        ];
    }

    /**
     * Undocumented function
     *
     * @return  array
     */
    private function getShippingFieldsOrder() : array
    {
        return [
            "first_name",
            "last_name",
            "email",
            "phone",
            "cpf",
            "postcode",
            "address_1",
            "number",
            "address_2",
            "neighborhood",
            "city",
            "state",
            "country",
        ];
    }

    /**
     * Undocumented function
     *
     * @param   string  $status
     * @return  array
     */
    private function getOrderStatus( string $status ) : array
    {
        return match ( $status )
        {
            "not_processed" => [
                "name"  => "Não Processado",
                "color" => "text-bg-secondary",
            ],
            "processed"     => [
                "name"  => "Processado",
                "color" => "text-bg-info",
            ],
            "approved"      => [
                "name"  => "Aprovado",
                "color" => "text-bg-success",
            ],
            "canceled"      => [
                "name"  => "Cancelado",
                "color" => "text-bg-danger",
            ]
        };
    }
}
