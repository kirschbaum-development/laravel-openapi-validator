<?php

/*
 * Laravel OpenAPI Validator Config
 */
return [
    /*
    |--------------------------------------------------------------------------
    | OpenAPI path
    |--------------------------------------------------------------------------
    |
    | This is the path to the OpenAPI spec (likely openapi.yaml, openapi.yml, or
    | openapi.json).
    |
    */
    'spec_path' => env('OPENAPI_PATH', base_path('openapi.yaml'))
];
