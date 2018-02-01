<?php

namespace Conklin\ElasticSearch\Console;

use Illuminate\Console\Command;
use Elasticsearch\ClientBuilder;
use Laravel\Scout\Events\ModelsFlushed;
use Illuminate\Contracts\Events\Dispatcher;

class ExplainCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elastic:explain {index? : The name of the elastic index}
                            {--m|model= : The model to retrieve the index for.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Give insight into elastic indexes";

    /**
     * Execute the console command.
     *
     * @param  \Illuminate\Contracts\Events\Dispatcher  $events
     * @return void
     */
    public function handle(Dispatcher $events)
    {
        $elastic = ClientBuilder::create()->setHosts([
            sprintf('%s:%s', config('scout.elastic.host'), config('scout.elastic.port'))
        ])->build();

        //Create Index
        $this->listIndices($elastic->indices()->getMapping([
            'index' => $this->argument('index')
        ]));
    }

    protected function listIndices($indices) {
        foreach($indices as $index => $config) {
            $this->info('Index: ' . $index);
            $this->listTypes($config['mappings']);
        }
    }

    protected function listTypes($types) {
        foreach($types as $type => $config) {
            $this->info('Type: ' . $type);
            $this->listProperties($config['properties']);
        }
    }

    protected function listProperties($properties) {
        $results = [];

        foreach($properties as $field => $map) {
            $results[] = [
                $field,
                $map['type']
            ];
        }

        $this->table([
            'field', 'type'
        ], $results);
    }
}
