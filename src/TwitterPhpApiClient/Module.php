<?php
/**
 * Created by PhpStorm.
 * User: stavarengo
 * Date: 14/03/19
 * Time: 17:05
 */

namespace Sta\TwitterPhpApiClient;


class Module
{
    public function getConfig()
    {
        $provider = new ConfigProvider();
        return [
            'service_manager' => $provider->getDependencyConfig(),
        ];
    }
}