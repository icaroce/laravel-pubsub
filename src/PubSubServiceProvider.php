<?php

namespace Superbalist\LaravelPubSub;

use Google\Cloud\PubSub\PubSubClient as GoogleCloudPubSubClient;
use GuzzleHttp\Client;
use Illuminate\Support\ServiceProvider;
use Superbalist\PubSub\PubSubAdapterInterface;

class PubSubServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/pubsub.php' => config_path('pubsub.php'),
        ]);
    }

    /**
     * Register bindings in the container.
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/pubsub.php', 'pubsub');

        $this->app->singleton('pubsub.factory', function ($app) {
            return new PubSubConnectionFactory($app);
        });

        $this->app->singleton('pubsub', function ($app) {
            return new PubSubManager($app, $app['pubsub.factory']);
        });

        $this->app->bind('pubsub.connection', PubSubAdapterInterface::class);

        $this->app->bind(PubSubAdapterInterface::class, function ($app) {
            $manager = $app['pubsub']; /* @var PubSubManager $manager */
            return $manager->connection();
        });

        $this->registerAdapterDependencies();

        $this->commands(SubscriberMakeCommand::class);
    }

    /**
     * Register adapter dependencies in the container.
     */
    protected function registerAdapterDependencies()
    {

        $this->app->bind('pubsub.gcloud.pub_sub_client', function ($app, $parameters) {
            return new GoogleCloudPubSubClient($parameters['config']);
        });


    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            'pubsub',
            'pubsub.factory',
            'pubsub.connection',
            'pubsub.gcloud.pub_sub_client',
        ];
    }
}
