<?php

if (!function_exists('array_get')) {
    /**
     * array_get gets an item from an array using "dot" notation
     * @param  \ArrayAccess|array  $array
     * @param  string|int  $key
     * @param  mixed  $default
     * @return mixed
     */
    function array_get($array, $key, $default = null)
    {
        return \Illuminate\Support\Arr::get($array, $key, $default);
    }
}