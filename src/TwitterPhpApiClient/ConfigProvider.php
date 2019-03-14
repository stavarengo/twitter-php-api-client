<?php

namespace Sta\TwitterPhpApiClient;

class ConfigProvider
{
    public function __invoke()
    {
        return [
            'dependencies' => $this->getDependencyConfig(),
        ];
    }

    /**
     * Provide default container dependency configuration.
     *
     * @return array
     */
    public function getDependencyConfig()
    {
        return [
            'factories' => [
                TwitterClient::class => TwitterClientFactory::class,
                \Sta\TwitterPhpApiClient\Middleware\TwitterRequestErrorHandler::class => \Sta\TwitterPhpApiClient\Middleware\TwitterRequestErrorHandlerFactory::class,
            ]
        ];
    }
}
