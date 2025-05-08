<?php

declare(strict_types = 1);

namespace App\Models\Marketplace;

use App\Casts\FormattedPrice;
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
        "external_id",
        "mkt_id",
        "max_price",
        "ord_id",
        "ord_status",
        "ord_price",
        "ord_shipping",
        "ord_created_at",
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
        "ord_price" => FormattedPrice::class,
        "ord_shipping" => FormattedPrice::class,
        "max_price" => FormattedPrice::class,
        "product_id" => "integer",
        "product_qntt" => "integer",
        "billing" => "array",
        "shipping" => "array",
    ];
}
