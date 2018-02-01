<?php

namespace Conklin\ElasticSearch\Pagination;

use Illuminate\Pagination\LengthAwarePaginator as BasePaginator;


class LengthAwarePaginator extends BasePaginator {

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        $data = [
            'current_page' => $this->currentPage(),
            'data' => $this->items->toArray(),
            'first_page_url' => $this->url(1),
            'from' => $this->firstItem(),
            'last_page' => $this->lastPage(),
            'last_page_url' => $this->url($this->lastPage()),
            'next_page_url' => $this->nextPageUrl(),
            'path' => $this->path,
            'per_page' => intval($this->perPage()),
            'prev_page_url' => $this->previousPageUrl(),
            'to' => $this->lastItem(),
            'total' => $this->total(),
        ];

        $map = config('elastic.responses.pagination');

        $result = [];

        foreach($data as $key => $value) {
            array_set($result, $map[$key], $value);
        }

        unset($data);
        unset($map);

        return $result;
    }
}