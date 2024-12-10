<?php
declare(strict_types=1);

namespace BEdita\I18n\Test\App;

use BEdita\I18n\I18nPlugin;
use BEdita\I18n\Middleware\I18nMiddleware;
use Cake\Http\BaseApplication;
use Cake\Http\MiddlewareQueue;
use Cake\Routing\Middleware\RoutingMiddleware;

/**
 * Application setup class.
 *
 * This defines the bootstrapping logic and middleware layers you
 * want to use in your application.
 */
class Application extends BaseApplication
{
    /**
     * @inheritDoc
     */
    public function bootstrap(): void
    {
        $this->addPlugin(I18nPlugin::class);
    }

    /**
     * @inheritDoc
     */
    public function middleware($middlewareQueue): MiddlewareQueue
    {
        $middlewareQueue->add(new I18nMiddleware());
        $middlewareQueue->add(new RoutingMiddleware($this));

        return $middlewareQueue;
    }
}
