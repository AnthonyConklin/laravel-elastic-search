<?php

namespace Conklin\ElasticSearch\Builder;

trait HandlesScopes {

    public function applyScopes(array $scopes)
    {
        $query = $this;
        foreach ($scopes as $scope => $value) {
            $decorator = $this->createScopeDecorator($scope);
            if ($this->isValidScopeDecorator($decorator)) {
                $query = app($decorator)->apply($this, $value);
            }
        }
        return $query;
    }

    protected function createScopeDecorator($name)
    {
        //Underscores to spaces,
        $name = str_replace('_', ' ', $name);
        //Uppercase words
        $name = ucwords($name);
        //Remove spaces
        $name = str_replace(' ', '', $name);
        //Construct fully qualified namespace.
        return sprintf('%s\\%sScope', $this->index->getElasticScopeNamespace(), $name);
    }

    protected function isValidScopeDecorator($decorator)
    {
        return class_exists($decorator);
    }

    protected function callScopeIfAvailable($scope, $arguments) {
        $scope = 'scopeElastic' . ucfirst($scope);

    	array_unshift($arguments, $this);

        if (method_exists($this->index, $scope)) {
        	return call_user_func_array([$this->index, $scope], $arguments);
        }
    }
}