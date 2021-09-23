<?php

namespace App\Helpers;

class Helper
{

    /**
     * For recursive diff of multiple arrays
     *
     * @param array $array
     * @param array ...$arrays
     * @return array
     */
    public static function array_diff_assoc_recursive(array $array, array ...$arrays)
    {
        $func = function ($array1, $array2) use (&$func)
        {
            $difference = [];
            foreach ($array1 as $key => $value)
            {
                if (is_array($value))
                {
                    if (!isset($array2[$key]) || !is_array($array2[$key]))
                    {
                        $difference[$key] = $value;
                    }
                    else
                    {
                        $new_diff = $func($value, $array2[$key]);
                        if (!empty($new_diff))
                        {
                            $difference[$key] = $new_diff;
                        }
                    }
                }
                else
                {
                    if (!array_key_exists($key, $array2) || $array2[$key] !== $value)
                    {
                        $difference[$key] = $value;
                    }
                }
            }

            return $difference;
        };

        $diffs = $array;

        foreach ($arrays as $_array)
        {
            $diffs = $func($diffs, $_array);
        }

        return $diffs;
    }

    public static function array_orderby()
    {
        $args = func_get_args();
        $data = array_shift($args);

        foreach ($args as $n => $field)
        {
            if (is_string($field))
            {
                $tmp = array();

                foreach ($data as $key => $row)
                {
                    $tmp[$key] = $row[$field];
                }

                $args[$n] = $tmp;
            }
        }

        $args[] = &$data;

        call_user_func_array('array_multisort', $args);

        return array_pop($args);
    }
}
