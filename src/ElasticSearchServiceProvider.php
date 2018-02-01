<?php

namespace Conklin\ElasticSearch;

use Elasticsearch\Client;
use Laravel\Scout\EngineManager;
use Illuminate\Support\ServiceProvider;
use Elasticsearch\ClientBuilder as Elastic;
use Conklin\ElasticSearch\Console\SearchCommand;
use Conklin\ElasticSearch\Console\ExplainCommand;
use Conklin\ElasticSearch\Builder\ElasticBuilder;

class ElasticSearchServiceProvider extends ServiceProvider
{
    /**
     * Booting the package.
     */
    public function boot()
    {
        $this->registerInContainer();
        $this->registerScoutEngine();
        $this->registerConfig();
        $this->registerCommands();
    }

    /**
     * Register the service provider.
     */
    public function register()
    {
    }

    /**
     * Register config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->publishes([
            __DIR__.'/Config/elastic.php' => config_path('elastic.php'),
        ], 'config');
        $this->mergeConfigFrom(
            __DIR__.'/Config/elastic.php', 'elastic'
        );
    }

    /**
     * Register commands that should be made available.
     *
     * @return void
     */
    public function registerCommands() 
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ExplainCommand::class,
                SearchCommand::class
            ]);
        }
    }

    /**
     * Register Elastic classes within IoC Container
     *
     * @return void
     */
    protected function registerInContainer() 
    {
        $this->app->singleton(Client::class, function ($app) {
            return Elastic::create()->setHosts(config('elastic.hosts'))->build();
        });

        $this->app->singleton('elastic', function ($app) {
            return new ElasticBuilder($app->get(Client::class));
        });
    }

    /**
     * Register ElasticEgine into Scouts EngineManager
     *
     * @return void
     */
    protected function registerScoutEngine() 
    {
        $this->app->get(EngineManager::class)->extend('elastic', function($app) {
            return new ElasticEngine($app->get(Client::class));
        });
    }

}