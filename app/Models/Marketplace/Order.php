<?php

declare(strict_types = 1);

namespace App\Models\Marketplace;

use Illuminate\Database\Eloquent\Model;

final class Order extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = "marketplace_orders";

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        "file_name",
        "strong_key",
        "mkt_id",
        "ord_id",
        "ord_status",
        "ord_price",
        "ord_shipping",
        "max_price",
        "product_id",
        "product_qntt",
        "product_name",
        "customer_id",
        "billing",
        "shipping",
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        "ord_price" => "float",
        "ord_shipping" => "float",
        "max_price" => "float",
        "product_id" => "integer",
        "product_qntt" => "integer",
        "billing" => "array",
        "shipping" => "array",
    ];
}
