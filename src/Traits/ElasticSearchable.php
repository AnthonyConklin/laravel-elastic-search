<?php

namespace Conklin\ElasticSearch\Traits;

use Laravel\Scout\Searchable;

trait ElasticSearchable {
    use Searchable, 
    SearchableTransformer;

    public function searchableShouldRefresh() {
        return isset($this->elasticRefresh) ? $this->elasticRefresh : config('elastic.refresh');
    }
}