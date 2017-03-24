<?php

namespace JsonApiHttp;

use Illuminate\Support\ServiceProvider;

use JsonApiHttp\Request;

class RequestServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *`
     * @var bool
     */
    protected $defer = true;

    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(Request::class, function ($app) {
            return Request::capture();
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [ Request::class ];
    }
}
