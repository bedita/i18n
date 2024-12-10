<?php
declare(strict_types=1);

namespace BEdita\I18n\Test\App;

use BEdita\I18n\I18nPlugin;
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
    public function bootstrapCli(): void
    {
        $this->addPlugin(I18nPlugin::class);
    }

    /**
     * @inheritDoc
     */
    public function middleware($middlewareQueue): MiddlewareQueue
    {
        $middlewareQueue = $middlewareQueue->add(new RoutingMiddleware($this));
        $plugin = $this->getPlugins()->get('BEdita/I18n');
        $middlewareQueue = $plugin->middleware($middlewareQueue);

        return $middlewareQueue;
    }
}
