<?php

declare(strict_types=1);

namespace Youvision\ThinkIdcard;

use think\Service as ThinkService;

class Service extends ThinkService
{
    public function register(): void
    {
        $this->mergeConfig();

        $app = $this->app;

        $app->bind('idcard', function () use ($app) {
            return new Idcard((array) $app->config->get('idcard', []));
        });

        $app->bind(Idcard::class, 'idcard');
    }

    protected function mergeConfig(): void
    {
        $default = require dirname(__DIR__) . '/config/idcard.php';
        $current = (array) $this->app->config->get('idcard', []);

        $this->app->config->set(array_merge($default, $current), 'idcard');
    }
}