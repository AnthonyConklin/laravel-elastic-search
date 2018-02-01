<?php

namespace Conklin\ElasticSearch\Builder;

trait HasParameters {
    

    public function addMustParam($param) {
        return $this->addParam($param, 'must');
    }

    public function addMustNotParam($param) {
        return $this->addParam($param, 'must_not');
    }

    public function addShouldParam($param) {
        return $this->addParam($param, 'should');
    }

    public function addFilterParam($param) {
        return $this->addParam($param, 'filter');
    }

    public function addSortParam($param) {
        return $this->addParam($param, 'sort');
    }

    public function getMustParams($param) {
        return $this->getParams('must');
    }

    public function getMustNotParams($param) {
        return $this->getParams('must_not');
    }

    public function getShouldParams($param) {
        return $this->getParams('should');
    }

    public function getFilterParams($param) {
        return $this->getParams('filter');
    }

    public function getSortParams($param) {
        return $this->getParams('sort');
    }

    public function hasParams($type) {
        if (!array_key_exists($type, $this->params)) {
            return false;
        }
        return count($this->params[$type]) > 0;
    }

    public function getParamTypes() {
        return array_keys($this->params);
    }

    protected function getParams($type = null) {
        $params = [];
        if(is_null($type)) {
            foreach($this->params as $type => $values) {
                $params[$type] = $this->getParams($type);
            }
            return $params;
        }
        foreach($this->params[$type] as $param) {
            $params[] = $param instanceof static ? $param->getQuery() : $param;
        }
        return $params;
    }

    protected function addParam($param, $type = 'must') {
        $this->params[$type][] = $param;
        return $this;
    }
}