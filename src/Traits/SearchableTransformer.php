<?php

namespace Conklin\ElasticSearch\Traits;

use Illuminate\Database\Eloquent\Model;

trait SearchableTransformer {
    
    /**
     * Transform model retrieved from elastic search.
     *
     * @param Model $data
     * @return array
     */
    public function searchableTransformer($data) {
        return $data->toArray();
    }
}