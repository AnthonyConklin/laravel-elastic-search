<?php

namespace Conklin\ElasticSearch\Builder;

use Carbon\Carbon;
use Elasticsearch\Client;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Database\Eloquent\Collection;
use Conklin\ElasticSearch\Pagination\LengthAwarePaginator;
use Conklin\ElasticSearch\Responses\ElasticResult;
use Illuminate\Database\Eloquent\Relations\Relation;

class ElasticBuilder {

    use HandlesScopes,
        HasParameters,
        Orderable,
        CollectsIndexes;

    /**
     * Match queries accept text/numerics/dates, analyzes them,
     * and constructs a query.
     *
     * Note, 'field' is the name of a field, you can substitute
     * the name of any field (including _all) instead.
     *
     * The match query is of type boolean. It means that the text
     * provided is analyzed and the analysis process constructs a
     * boolean query from the provided text. The operator flag can
     * be set to or or and to control the boolean clauses
     * (defaults to or). The minimum number of optional should
     * clauses to match can be set using the minimum_should_match
     * parameter.
     */
	const QUERY_MATCH = 'match';
    /**
     * The match_phrase query analyzes the text and creates a phrase
     * query out of the analyzed text.
     *
     * A phrase query matches terms up to a configurable slop
     * (which defaults to 0) in any order. Transposed terms have a
     * slop of 2.
     *
     * The analyzer can be set to control which analyzer will perform
     * the analysis process on the text. It defaults to the field
     * explicit mapping definition, or the default search analyzer.
     */
    const QUERY_MATCH_PHRASE = 'match_phrase';

    /**
     * The match_phrase_prefix is the same as match_phrase, except that
     * it allows for prefix matches on the last term in the text.
     *
     * It accepts the same parameters as the phrase type. In addition,
     * it also accepts a max_expansions parameter (default 50) that
     * can control to how many suffixes the last term will be expanded.
     * It is highly recommended to set it to an acceptable value to
     * control the execution time of the query.
     */
	const QUERY_MATCH_PHRASE_PREFIX = 'match_phrase_prefix';

    /**
     * The multi_match query builds on the match query to allow multi-field queries.
     *
     * Fields can be specified with wildcards, eg: "*_name"
     *
     * Individual fields can be boosted with the caret (^) notation, eg: "subject^3"
     *
     * Types of Multi-Match query:
     * ---------------------------------------------------------------------------
     * best_fields: (default) Finds documents which match any field, but uses
     * the _score from the best field.
     * ---------------------------------------------------------------------------
     * most_fields: Finds documents which match any field and combines the _score
     * from each field.
     * ---------------------------------------------------------------------------
     * cross_fields: Treats fields with the same analyzer as though they were one
     * big field. Looks for each word in any field.
     * ---------------------------------------------------------------------------
     * phrase: Runs a match_phrase query on each field and combines the _score
     * from each field.
     * ---------------------------------------------------------------------------
     * phrase_prefix: Runs a match_phrase_prefix query on each field and combines
     * the _score from each field.
     */
    const QUERY_MULTI_MATCH = 'multi_match';

    /**
     * The common terms query is a modern alternative to stopwords which improves
     * the precision and recall of search results (by taking stopwords into account),
     * without sacrificing performance.
     */
    const QUERY_COMMON = 'common';

    /**
     * List of Elastic Indexes were performing searches on.
     *
     * @var array
     */
    protected $indexes = [];


    /**
     * Automatically reset query?
     *
     * @var boolean
     */
    protected $resetAfterQuery = true;

    /**
     * An array to map models to their elastic indexes.
     *
     * @var array
     */
    public $indexMap = [];

    protected $params = [
        'must'      => [],
        'should'    => [],
        'filter'    => [],
        'sort'      => [],
        'must_not'  => []
    ];

	public function __construct(Client $client)
	{
        $this->client = $client;
    }

    public function query($string) {
    	$this->query = $string;
    	return $this;
    }

    public function where($field, $value, $options = [], $type = 'term')
    {
	    return $this;
    }

    public function whereIn($field, $values = [], $options = [], $type = 'terms') 
    {

    }

    /**
     * Set or get the morph map for polymorphic relations.
     *
     * @param  array|null  $map
     * @param  bool  $merge
     * @return array
     */
    public function indexMap(array $map = null, $merge = true)
    {
        $map = $this->buildIndexMapFromModels($map);

        if (is_array($map)) {
            $this->indexMap = $merge && $this->indexMap
                            ? $map + $this->indexMap : $map;
        }

        return $this->indexMap;
    }

    /**
     * Builds a table-keyed array from model class names.
     *
     * @param  string[]|null  $models
     * @return array|null
     */
    protected function buildIndexMapFromModels(array $models = null)
    {
        if (is_null($models)) {
            return $models;
        }

        if(Arr::isAssoc($models)) {
            return $this->applyIndexPrefix($models);
        }

        return array_combine(array_map(function ($model) {
            return $this->applyIndexPrefix((new $model)->searchableAs());
        }, $models), $models);
    }

    public function applyIndexPrefix($indexes) 
    {
        $prefix = config('scout.prefix');

        if (is_array($indexes)) {
            if(Arr::isAssoc($indexes)) {
                return array_combine($this->applyindexPrefix(array_keys($indexes)), array_values($indexes));
            } else {
                return array_map(function($index) {
                    return $this->applyIndexPrefix($index);
                }, $indexes);
            }
        }
        return substr($indexes, 0, strlen($prefix)) === $prefix ? $indexes : $prefix . $indexes;
    }

    public function indexHandler($index) 
    {
        if (!array_key_exists($index, $this->indexMap)) {
            return false;
        }
        return app($this->indexMap[$index]);
    }
    /*
     * Query grouping methods
     * ---------------------------------------------
     */

    /**
     * Create a must query grouping.
     * Documents must match the critera supplied.
     * 
     * @param callable $callback
     * @return static
     */
    public function must(callable $callback) 
    {
        return $this->addMustParam(call_user_func($callback, with(new static($this->client))));
    }

    /**
     * Create a must NOT query grouping.
     * Documents must NOT match the critera supplied.
     * 
     * @param callable $callback
     * @return static
     */
    public function mustNot(callable $callback) 
    {
        return $this->addMustNotParam(call_user_func($callback, with(new static($this->client))));
    }

    /**
     * Create a should query grouping.
     * Documents should match atleast one of the critera supplied.
     * 
     * @param callable $callback
     * @return static
     */
    public function should(callable $callback) 
    {
        return $this->addShouldParam(call_user_func($callback, with(new static($this->client))));
    }

    /**
     * In filter context, a query clause answers the question 
     * “Does this document match this query clause?” The answer 
     * is a simple Yes or No — no scores are calculated. Filter 
     * context is mostly used for filtering structured data, e.g.
     * 
     * Does this timestamp fall into the range 2015 to 2016?
     * Is the status field set to "published"?
     * 
     * @param callable $callback
     * @return static
     */
    public function filter(callable $callback) 
    {
        return $this->addFilterParam(call_user_func($callback, with(new static($this->client))));
    }

    /*
     * MATCH Queries
     * ---------------------------------------------
     */
    public function match($field, $value = null, $options = [], $matchType = 'match', $operator = 'must') 
    {
        if (is_callable($field)) {
            $builder = call_user_func($field, new static($this->index));
            if ($builder instanceof ElasticBuilder) {
                $this->params[$operator][] = $builder;
            }
            return $this;
        }
        if (is_array($field)) {
            $builder = new static($this->index);
            foreach($field as $key => $value) {
                $builder->match($key, $value, $options, $matchType, $operator);
            }
            $this->params[$operator][] = $builder;
            return $this;
        }

        $query = [];

        $query[$matchType] = [];

        $query[$matchType][$field] = array_merge($options, [
            'query' => $value
        ]);

        $this->params[$operator][] = $query;

        return $this;
    }

    public function search($value, array $fields = [], $options = []) 
    {
        
        if(count($fields) && Arr::isAssoc($fields)) {
            $fieldWeights = [];
            foreach($fields as $field => $weight) {
                $weight = intval($weight) > 0 ? '^' . intval($weight) : '';
                $fieldWeights[] = $field . $weight;
            }
            $fields = $fieldWeights;
        }
        $this->params['must'][] = [
            'query_string' => array_merge(
                [ 
                    'query' => $value,
                    'default_operator' => 'AND',
                    'fields' => $fields
                ], 
                count($fields) > 0 ? [
                    'fields' => $fields
                ] : [],
                $options
            )
        ];
        return $this;
    }

    public function mustMatch($field, $value, $options = [], $matchType = 'match') 
    {
        return $this->match($field, $value, $options, $matchType, 'must');
    }

    public function mustMatchPhrase($field, $value, $options = []) 
    {
        return $this->mustMatch($field, $value, $options, self::QUERY_MATCH_PHRASE);
    }

    public function mustMatchPhrasePrefix($field, $value, $options = []) 
    {
        return $this->mustMatch($field, $value, $options, self::QUERY_MATCH_PHRASE_PREFIX);
    }

    public function mustMatchMulti($field, $value, $options = []) 
    {
        return $this->mustMatch($field, $value, $options, self::QUERY_MULTI_MATCH);
    }

    public function shouldMatch($field, $value, $options = [], $matchType = 'match') 
    {
        return $this->match($field, $value, $options, $matchType, 'should');
    }

    public function shouldMatchPhrase($field, $value, $options = []) 
    {
        return $this->shouldMatch($field, $value, $options, self::QUERY_MATCH_PHRASE);
    }

    public function shouldMatchPhrasePrefix($field, $value, $options = []) 
    {
        return $this->shouldMatch($field, $value, $options, self::QUERY_MATCH_PHRASE_PREFIX);
    }

    public function shouldMatchMulti($field, $value, $options = []) 
    {
        return $this->shouldMatch($field, $value, $options, self::QUERY_MULTI_MATCH);
    }

    public function whereTerm ($field, $value, $options = [])
    {
        return $this->whereMatch($field, $value, $options, self::QUERY_MULTI_MATCH);
    }

    public function getQuery() 
    {
        $query = [
            'bool' => []
        ];

        foreach(array_keys($this->params) as $type) {
            if($this->hasParams($type)) {
                if ($type !== 'sort') {
                    $query['bool'][$type] = $this->getParams($type);
                }
            }
        }

        if (!count($query['bool'])) {
            return [];
        }

        return $query;
    }

    public function getNonQueryParams() 
    {
        $query = [];

        foreach(array_keys($this->params) as $type) {
            if($this->hasParams($type)) {
                if (!in_array($type, ['must', 'should', 'filter'])) {
                    $query[$type] = $this->getParams($type);
                }
            }
        }

        return $query;
    }

    /**
     * Alias for index()
     *
     * @param string|array $index
     * @return static
     */
    public function on($index) 
    {
        return $this->index($index);
    }

    /**
     * sets the indexes to search on.
     *
     * @param string|array $index
     * @return static
     */
    public function index($index) 
    {

        if (!is_array($index)) {
            $index = explode(',', $index);
        }

        $index = $this->applyIndexPrefix($index);

        if (Arr::isAssoc($index)) {
            $this->indexWeights = $index;
            $index = array_keys($index);
        }

        $this->indexes = $index;
        return $this;
    }

    /**
     * Get list of indexes to search on.
     *
     * @return void
     */
    public function getIndexes() 
    {
        
        if (!count($this->indexes)) {
            /** 
             * If a prefix has been set, use that with a wild card.
             * If a prefix has not been set use some wildcards while 
             * diregarding elastic system indexes with "-.*"
             */
            return !strlen(config('scout.prefix')) ? '*,-.*' : config('scout.prefix') . '*';
        }
        return implode(',', $this->indexes);
    }

    public function getFullQuery($params = [])
    {
        $query = $this->getQuery();
        $body = $this->getNonQueryParams();
        if (count($query)) {
            $body = array_merge($body, [
                'query' => $query
            ]);
        }
        return array_merge($params, [
            'index' => $this->getIndexes(),
            'body' => $body
        ]);
    }

    public function getRaw() 
    {
        return $this->client->search($this->getFullQuery());
    }

    public function resetParams() 
    {
        foreach($this->params as $key => $value) {
            $this->params[$key] = [];
        }
        return $this;
    }

    public function reset() 
    {
        $this->indexes = [];
        $this->resetParams();
    }

    /**
     * Get results for search.
     *
     * @return ElasticResult
     */
    public function get() 
    {
        $results = $this->client->search($this->getFullQuery());

        if($this->resetAfterQuery) {
            $this->reset();
        }

        return new ElasticResult($results);
    }

    /**
     * Undocumented function
     *
     * @param integer $page
     * @param integer $perPage
     * @return LengthAwarePaginator
     */
    public function paginate($page = 1, $perPage = 30) 
    {
        $results = $this->client->search($this->getFullQuery([
            'size' => $perPage,
            'from' => $perPage * ($page-1)
        ]));

        if($this->resetAfterQuery) {
            $this->reset();
        }

        $result = new ElasticResult($results);

        return with(new LengthAwarePaginator($result, $result->total(), $perPage, $page, [
            'path' => config('app.url') . '/' . request()->path()
        ]))->appends(request()->query());
    }

    public function __call($name, $arguments)
    {
    	$this->callScopeIfAvailable($name, $arguments);

        return $this;
    }
}