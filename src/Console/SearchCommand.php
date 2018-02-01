<?php

namespace Conklin\ElasticSearch\Console;

use Illuminate\Console\Command;
use Laravel\Scout\Events\ModelsFlushed;
use Illuminate\Contracts\Events\Dispatcher;

class SearchCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elastic:search {query : What to search for...}
                            {--i|index= : The index to search on.}
                            {--m|model= : The model to search.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Quick searches through index";

    /**
     * Execute the console command.
     *
     * @param  \Illuminate\Contracts\Events\Dispatcher  $events
     * @return void
     */
    public function handle(Dispatcher $events)
    {
        
    }
}
