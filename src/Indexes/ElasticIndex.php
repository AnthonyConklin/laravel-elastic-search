<?php

namespace Conklin\ElasticSearch\Indexes;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;

class ElasticIndex {

    /**
     * Available fields on index, value is equal to field type
     * @var array
     */
    protected $fields = [];

    /**
     * Customized weighted fields.
     * @var array
     */
    protected $fieldWeights = [];

    /**
     * Name of the ElasticSearch index
     *
     * @var string
     */
    protected $index;

    /**
     * @var
     */
    protected $model;

    /**
     * Get a field with aggregated information.
     *
     * @param $field
     * @return object|null
     */
    public function getField($field)
    {
        if (array_key_exists($field, $this->fields)) {
            $result = [
                'type'   => $this->fields[$field],
                'weight' => $this->getFieldWeight($field)
            ];

            return json_decode(json_encode($result));
        }

        return null;
    }

    /**
     * @param $type
     * @return array
     */
    public function getFieldsByType($type) {
        $fields = [];

        foreach($this->fields as $field => $fieldType) {
            if($type === $fieldType) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    /**
     * @param $field
     * @param null $default
     * @return mixed|null
     */
    public function getFieldWeight($field, $default = null) {
        return array_key_exists($field, $this->fieldWeights) ? $this->fieldWeights[$field] : $default;
    }

    /**
     * @return Model
     * @throws \Exception
     */
    public function getModel() {
        if (!$this->model) {
           throw new \Exception('No model set on elastic index');
        }
        return app($this->model);
    }

    /**
     * Return the index mapping
     * @return array
     */
    public function structure()
    {
        return array_map(function($type) {
            return [
                'type' => $type
            ];
        }, $this->fields);
    }

    public function getScopeNamespace() {
        $reflector = new \ReflectionClass($this);
        return $reflector->getNamespaceName(). '\\Scopes';
    }

    /**
     * @return mixed
     */
    public function getIndex()
    {
        return Config::get('base.search.index_prefix') . $this->index;
    }

    /**
     *
     * @param Collection $results
     * @return Collection
     */
    public function transform(Collection $results) {
        return $results;
    }

    /**
     * Magic method to fetch fields on index.
     *
     * @param $name
     * @return null|object
     */
    public function __get($name)
    {
        return $this->getField($name);
    }
}