<?php
/**
 * Project ${PROJECT_URL}
 *
 * @link      ${GITHUB_URL} Source code
 */

namespace Sta\TwitterPhpApiClient;

use Cely\TwitterClient\Middleware\TwitterRequestErrorHandler;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use Sta\TwitterPhpApiClient\Exception\MissingTwitterConfiguration;

class TwitterClientFactory
{

    public function createService($serviceLocator)
    {
        return $this->__invoke($serviceLocator, self::class);
    }

    /**
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     *
     * @return TwitterClient
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container, $requestedName = null, $options = null)
    {
        if (!is_array($options)) {
            $options = [];
        }

        $appConfig = [];
        if (isset($options['config'])) {
            $appConfig = $options['config'];
        } else if ($container->has('config')) {
            $appConfig = $container->get('config');
        }

        $config = isset($appConfig['Sta\TwitterPhpApiClient']) ? $appConfig['Sta\TwitterPhpApiClient'] : [];

        $cachePool = null;
        if (isset($options['cachePool'])) {
            $cachePool = $options['cachePool'];
        } else if (isset($config['cachePoolFactoryName']) && $config['cachePoolFactoryName']) {
            $cachePool = $container->get($config['cachePoolFactoryName']);
        } else if ($container->has(CacheItemPoolInterface::class)) {
            $cachePool = $container->get(CacheItemPoolInterface::class);
        }

        return new TwitterClient($container->get(TwitterRequestErrorHandler::class), $cachePool);
    }
}
