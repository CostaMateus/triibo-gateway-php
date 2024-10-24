<?php

namespace App\Models\Vtex;

class Product extends Model
{
    protected $table    = "vtex_products";

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
        "meta",
        "data"
    ];
}
