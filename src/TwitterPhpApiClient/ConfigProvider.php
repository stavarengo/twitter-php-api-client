<?php

namespace Sta\TwitterPhpApiClient;

class ConfigProvider
{
    public function __invoke()
    {
        return $this->getConfig();
    }

    public function getConfig()
    {
        $config = [
            'dependencies' => $this->getDependencyConfig(),
        ];

        return $config;
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
                \Cely\TwitterClient\Middleware\TwitterRequestErrorHandler::class => \Cely\TwitterClient\Middleware\TwitterRequestErrorHandlerFactory::class,
            ],
            'aliases' => [
            ]
        ];
    }
}
