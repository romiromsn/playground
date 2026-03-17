<?php
declare(strict_types=1);

namespace CakeHx;

use Cake\Core\BasePlugin;
use Cake\Core\PluginApplicationInterface;
use Cake\Http\MiddlewareQueue;
use CakeHx\Middleware\HxMiddleware;

class CakeHxPlugin extends BasePlugin
{
    public function bootstrap(PluginApplicationInterface $app): void
    {
        parent::bootstrap($app);
    }

    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        $middlewareQueue->add(new HxMiddleware());

        return $middlewareQueue;
    }
}
