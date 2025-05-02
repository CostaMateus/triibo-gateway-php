<?php

declare(strict_types = 1);

namespace App\Models\Vtex;

use Illuminate\Database\Eloquent\Model;

final class ProductNotification extends Model
{
    protected $table = "vtex_product_notifications";

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        "sku",
        "origin",
        "action",
        "status",
        "description",
        "meta",
        "data",
    ];
}
