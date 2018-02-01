<?php

namespace Conklin\ElasticSearch\Responses;

use JsonSerializable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Support\Jsonable;
use Conklin\ElasticSearch\Facades\Elastic;
use Illuminate\Contracts\Support\Arrayable;
use Conklin\ElasticSearch\Traits\ElasticSearchable;

class ElasticResult implements Arrayable, Jsonable, JsonSerializable {

    protected $result;
    protected $data;
    protected $meta;

    /**
     * Undocumented function
     *
     * @param array $result
     */
    public function __construct(array $result) {
        $this->result = $result;
        $this->parseResults();
    }

    public function parseResults() {
        $this->parseMeta();
        $this->parseData();
    }

    public function parseMeta() {
        $meta = $this->result;

        $meta['total'] = $meta['hits']['total'];
        $meta['maxScore'] = $meta['hits']['max_score'];
        $meta['count'] = count($meta['hits']['hits']);
        unset($meta['hits']);
        $this->meta = json_decode(json_encode($meta));

        return $this;
    }

    public function getMeta() {
        return $this->meta;
    }

    public function count() {
        return $this->meta->count;
    }

    public function total() {
        return $this->meta->total;
    }

    public function maxScore() {
        return $this->meta->maxScore;
    }

    public function time($withUnit = true) {
        return $this->meta->took . $withUnit ? 'ms' : '';
    }

    public function getRaw() {
        return $this->result;
    }

    /**
     * Undocumented function
     *
     * @return static
     */
    public function parseData() 
    {
        // Group results by index, each index will have different handlers/transformers etc.
        $this->data = collect($this->result['hits']['hits'])->map(function($item, $key) {
            // Preserve current order to insure it is the same after parsing.
            $item['_order'] = $key;
            return $item;
        })->groupBy('_index')->map(function($hits, $index) {

            $handler = Elastic::indexHandler($index);
            $keyName = $handler->getKeyName();
            $refresh = $this->indexShouldBeRefreshed($index);

            //Key by id for easy insertion.
            $hits = $hits->keyBy('_source.' . $keyName);
            
            if ($refresh) {
                // Get a fresh set of data from database
                $data = $this->getDataFromHandler($handler, $hits->pluck('_source.' . $keyName));
                /** 
                 * @TODO: Eager load any requested includes...
                 * @Question: How to determine which group the eager load is directed
                 * towards.
                 */
                $hits = $hits->map(function($row, $key) use ($handler, $data) {
                    $row['_source'] = $data->get($key);
                    return $row;
                });
            } else {
                // Replace plain array with model representing the data.
                $hits = $hits->map(function($row, $key) use ($handler) {

                    $row['_source'] = $this->fillNewModelInstance($handler, $row['_source']);
                    return $row;
                });
            }
            // Apply any transformers

            $hits = $hits->map(function($row, $key) use ($handler) {
                if(is_null($row['_source'])) {
                    dd($row);
                }
                $row['_source'] = $row['_source']->searchableTransformer($row['_source']);
                return $row;
            });

            return $hits;
        })->flatMap(function($values) {
            return $values;
        })->sortBy('_order')->values()->map(function ($row, $key) {
            return $row['_source'];
        });
        
        return $this;
    }

    public function fillNewModelInstance($handler, array $data) {
        if ($handler instanceof Model) {
            foreach($data as $key => $value) {
                if(method_exists($handler, $key)) {
                    unset($data[$key]);
                }
            }
            return $handler->newInstance([], true)->forceFill($data);
        }
        if ($handler instanceof ElasticIndex) {

            $elasticModel = $handler->getModel();

            if (!$elasticModel) {
                return $handler->setData($data);
            }

            if(!($elasticModel instanceof Model)) {
                $elasticModel = app($elasticModel);
            }

            return $this->getDataFromHandler($elasticModel, $data);
        }
        throw new \Exception('Handler is neither an Eloquent Model or an ElasticIndex. There is no way to retrieve new data from this index.');
    }

    /**
     * Get Data for refresh request.
     *
     * @param [type] $handler
     * @param [type] $ids
     * @return void
     */
    public function getDataFromHandler($handler, $ids) 
    {
        if ($handler instanceof Model) {
            return $handler->whereIn($handler->getKeyName(), $ids)->get()->keyBy($handler->getKeyName());
        }
        if ($handler instanceof ElasticIndex) {

            $elasticModel = $handler->getModel();

            if (!$elasticModel) {
                return $handler->getData($ids);
            }

            if(!($elasticModel instanceof Model)) {
                $elasticModel = app($elasticModel);
            }

            return $this->getDataFromHandler($elasticModel, $ids);
        }
        throw new \Exception('Handler is neither an Eloquent Model or an ElasticIndex. There is no way to retrieve new data from this index.');
    }

    public function indexShouldBeRefreshed($index) {
        $handler = Elastic::indexHandler($index);

        // Check if handler uses ElasticSearchable.
        if(array_key_exists(ElasticSearchable::class, class_uses($handler))) {
            return $handler->searchableShouldRefresh();
        }

        //Check if the property is available, and its a model.
        if ($handler->elasticRefresh && $handler instanceof Model) {
            return $handler->elasticRefresh;
        }
        // We don't know how to refresh it even if its requested.
        return false;
    }

    public function toArray() {
        return $this->data->toArray();
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->data->jsonSerialize();
    }

    /**
     * Get the collection of items as JSON.
     *
     * @param  int  $options
     * @return string
     */
    public function toJson($options = 0)
    {
        return $this->data->toJson($options);
    }

    public function transform() {
        // How do we find the type?
    }

}
