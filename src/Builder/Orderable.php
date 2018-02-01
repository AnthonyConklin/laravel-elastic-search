<?php

namespace Conklin\ElasticSearch\Builder;

trait Orderable {

    public function sortBy($field, $order = 'desc', $mode = false) {
        $sort = [];
        $sort[$field] = [
            'order' => $this->parseSortDirection($order)
        ];

        $mode = $this->parseSortMode($mode);
        if ($mode) {
           $sort[$field]['mode'] = $mode;
        }

        $this->addSortParam($sort);

        return $this;
    }

    public function orderBy($field, $order = 'desc', $mode = false) {
        return $this->sortBy($field, $order, $mode);
    }

    public function sortByScore($order = 'desc')
    {
        return $this->sortby('_score', $order);
    }

    public function orderByScore($order = 'desc')
    {
        return $this->sortByScore($order);
    }

    public function sortByDistance($field, $value, $order = 'asc', $mode = 'min', $unit = 'm', $mostAccurate = true) {
        $sort = [
            '_geo_distance' => [
                'order' => $this->parseSortDirection($order),
                'unit' => $unit,
                'mode' => $this->parseSortMode($mode),
                'distance_type' => $mostAccurate ? 'arc' : 'plane'
            ]
        ];
        $sort['_geo_distance'][$field] = $value;

        $this->addSortParam($sort);

        return $this;
    }

    public function orderByDistance($field, $value, $order = 'asc', $mode = 'min', $unit = 'm', $mostAccurate = true)
    {
	    return $this->sortByDistance($field, $value, $order, $mode, $unit, $mostAccurate);
    }

    protected function parseSortDirection($direction) {
        return !in_array($direction, ['desc', 'asc']) ? 'desc' : $direction;
    }

    protected function parseSortMode($mode) {
        return !in_array($mode, ['min', 'max', 'sum', 'avg', 'median']) ? false : $mode;
    }

    abstract public function addSortParam($param);
}