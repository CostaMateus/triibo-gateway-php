<?php

namespace App\Models\Marketplace;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    /**
     * The table associated with the model.
     *
     * @var     string
     */
    protected $table = "marketplace_orders";

    /**
     * The attributes that are mass assignable.
     *
     * @var     array
     */
    protected $fillable = [
        "file_name",
        "mkt_id",
        "ord_id",
        "ord_status",
        "max_price",
        "product_id",
        "product_qntt",
        "product_name",
        "billing",
        "shipping",
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var     array
     */
    protected $casts = [
        "max_price"    => "float",
        "product_id"   => "integer",
        "product_qntt" => "integer",
        "billing"      => "array",
        "shipping"     => "array",
    ];
}
