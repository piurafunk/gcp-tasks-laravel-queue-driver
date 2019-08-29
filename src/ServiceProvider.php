<?php

namespace Piurafunk\GcpTasksQueueDriver;

use Google\Cloud\Tasks\V2\CloudTasksClient;
use Illuminate\Support\ServiceProvider as IlluminateServiceProvider;

class ServiceProvider extends IlluminateServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        /** @var \Illuminate\Queue\QueueManager $manager */
        $manager = $this->app['queue'];

        $manager->addConnector('gcp-tasks', function () {
            return new GcpTasksConnector;
        });

        $this->app->singleton(CloudTasksClient::class, function ($app, $config) {
            return new CloudTasksClient($config);
        });
    }
}
