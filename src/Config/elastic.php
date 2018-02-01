<?php


return [
    /*
    |--------------------------------------------------------------------------
    | ElasticSearch Hosts
    |--------------------------------------------------------------------------
    |
    | This value an array of available elastic nodes within your system. Since
    | the value is an array and is being pulled from your .env file the correct 
    | format to use is: 
    |
    | {host_address1}:{port},{host_address2}:{port}
    | 
    | ie: ELASTIC_HOSTS=192.168.0.1:9200,localhost:9200
    | 
    | Alternatively you may replace the line below with an array of hosts.
    | ie: 'hosts' => ['192.168.0.1:9200', 'localhost:9200', ...]
    */
    'hosts' => explode(',', env('ELASTIC_HOSTS', 'localhost:9200')),

    /*
    |--------------------------------------------------------------------------
    | Refresh data retrieved from Elastic
    |--------------------------------------------------------------------------
    |
    | After pulling results from Elastic, you may choose to pull a fresh result
    | set down from your database. If you only store partial data this is going
    | to be needed, however if you store the entire field set from database in 
    | elastic index then there isn't really a need to re-pull data. Not 
    | refreshing is more efficient, especially when searching multiple indexes.
    |
    | This option can be overidden on each model or index definition by adding
    | a $elasticRefresh property to the class:
    |
    | protected $elasticRefresh = true|false;
    |
    */
    'refresh' => false,
    /*
    |--------------------------------------------------------------------------
    | Customize paginated result output.
    |--------------------------------------------------------------------------
    |
    | The following keys are what laravel paginator uses as its structure for 
    | outputing paginated data. As it doesn't allow customizing without extending
    | the Paginator class, this makes it easy to reformat to whatever you'd like 
    | using array dot notation.
    |
    */
    'responses' => [
        'pagination' => [
            'current_page'      => 'current_page',
            'data'              => 'data',
            'first_page_url'    => 'first_page_url',
            'from'              => 'from',
            'last_page'         => 'last_page',
            'last_page_url'     => 'last_page_url',
            'next_page_url'     => 'next_page_url',
            'path'              => 'path',
            'per_page'          => 'per_page',
            'prev_page_url'     => 'prev_page_url',
            'to'                => 'to',
            'total'             => 'total',
        ]
    ]
];