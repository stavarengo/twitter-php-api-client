<?php
/**
 * shelob Project ${PROJECT_URL}
 *
 * @link      ${GITHUB_URL} Source code
 */

namespace Cely\TwitterClient\Middleware;

use Cely\Bombadil\LoggerHelper;
use Interop\Container\ContainerInterface;

class TwitterRequestErrorHandlerFactory
{
    public function __invoke(ContainerInterface $container)
    {
        /** @var LoggerHelper $logger */
        $logger = $container->get(LoggerHelper::class);

        return new TwitterRequestErrorHandler($logger);
    }
}
