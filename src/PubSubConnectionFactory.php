<?php

namespace Superbalist\LaravelPubSub;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use Superbalist\PubSub\Adapters\DevNullPubSubAdapter;
use Superbalist\PubSub\Adapters\LocalPubSubAdapter;
use Superbalist\PubSub\GoogleCloud\GoogleCloudPubSubAdapter;
use Superbalist\PubSub\PubSubAdapterInterface;

class PubSubConnectionFactory
{
    /**
     * @var Container
     */
    protected $container;

    /**
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Factory a PubSubAdapterInterface.
     *
     * @param string $driver
     * @param array $config
     *
     * @return PubSubAdapterInterface
     */
    public function make($driver, array $config = [])
    {
        switch ($driver) {
            case '/dev/null':
                return new DevNullPubSubAdapter();
            case 'local':
                return new LocalPubSubAdapter();
            case 'gcloud':
                return $this->makeGoogleCloudAdapter($config);
        }

        throw new InvalidArgumentException(sprintf('The driver [%s] is not supported.', $driver));
    }



    /**
     * Factory a GoogleCloudPubSubAdapter.
     *
     * @param array $config
     *
     * @return GoogleCloudPubSubAdapter
     */
    protected function makeGoogleCloudAdapter(array $config)
    {
        $clientConfig = [
            'projectId' => $config['project_id'],
            'keyFilePath' => $config['key_file'],
        ];
        if (isset($config['auth_cache'])) {
            $clientConfig['authCache'] = $this->container->make($config['auth_cache']);
        }

        $client = $this->container->makeWith('pubsub.gcloud.pub_sub_client', ['config' => $clientConfig]);

        $clientIdentifier = Arr::get($config, 'client_identifier');
        $autoCreateTopics = Arr::get($config, 'auto_create_topics', true);
        $autoCreateSubscriptions = Arr::get($config, 'auto_create_subscriptions', true);
        $backgroundBatching = Arr::get($config, 'background_batching', false);
        $backgroundDaemon = Arr::get($config, 'background_daemon', false);

        if ($backgroundDaemon) {
            putenv('IS_BATCH_DAEMON_RUNNING=true');
        }
        return new GoogleCloudPubSubAdapter(
            $client,
            $clientIdentifier,
            $autoCreateTopics,
            $autoCreateSubscriptions,
            $backgroundBatching
        );
    }

    /**
     * Factory a HTTPPubSubAdapter.
     *
     * @param array $config
     *
     * @return HTTPPubSubAdapter
     */
    protected function makeHTTPAdapter(array $config)
    {
        $client = $this->container->make('pubsub.http.client');
        $adapter = $this->make(
            $config['subscribe_connection_config']['driver'],
            $config['subscribe_connection_config']
        );
        return new HTTPPubSubAdapter($client, $config['uri'], $adapter);
    }
}
