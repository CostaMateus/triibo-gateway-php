<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class FormattedPrice implements CastsAttributes
{
    /**
     * Cast the given value, float to string.
     *
     * @param Model $model
     * @param string $key
     * @param mixed $value
     * @param array $attributes
     *
     * @return string
     */
    public function get( Model $model, string $key, mixed $value, array $attributes ) : string
    {
        if ( !$value )
            return "0,00";

        return number_format( $value, 2, ",", "." );
    }

    /**
     * Prepare the given value for storage, string to float.
     *
     * @param Model $model
     * @param string $key
     * @param mixed $value
     * @param array $attributes
     *
     * @return float
     */
    public function set( Model $model, string $key, mixed $value, array $attributes ) : float
    {
        if ( is_float( $value ) )
            return $value;

        $value = str_replace( ".", "", $value );
        $value = str_replace( ",", ".", $value );

        return (float) $value;
    }
}
