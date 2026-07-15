<?php
declare(strict_types=1);

namespace App;

use App\Middleware\CorsMiddleware;
use App\Middleware\HostHeaderMiddleware;
use Cake\Core\Configure;
use Cake\Core\ContainerInterface;
use Cake\Datasource\FactoryLocator;
use Cake\Error\Middleware\ErrorHandlerMiddleware;
use Cake\Event\EventManagerInterface;
use Cake\Http\BaseApplication;
use Cake\Http\Middleware\BodyParserMiddleware;
use Cake\Http\Middleware\CsrfProtectionMiddleware;
use Cake\Http\MiddlewareQueue;
use Cake\ORM\Locator\TableLocator;
use Cake\Routing\Middleware\AssetMiddleware;
use Cake\Routing\Middleware\RoutingMiddleware;
use Psr\Http\Message\ServerRequestInterface;

class Application extends BaseApplication
{
    public function bootstrap(): void
    {
        parent::bootstrap();
        FactoryLocator::add('Table', (new TableLocator())->allowFallbackClass(false));
    }

    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        $csrf = new CsrfProtectionMiddleware(['httponly' => true]);
        $csrf->skipCheckCallback(
            fn (ServerRequestInterface $request): bool => str_starts_with($request->getUri()->getPath(), '/api/'),
        );

        $middlewareQueue
            ->add(new ErrorHandlerMiddleware(Configure::read('Error'), $this))
            ->add(new CorsMiddleware())
            ->add(new HostHeaderMiddleware())
            ->add(new AssetMiddleware(['cacheTime' => Configure::read('Asset.cacheTime')]))
            ->add(new RoutingMiddleware($this))
            ->add(new BodyParserMiddleware())
            ->add($csrf);

        return $middlewareQueue;
    }

    public function services(ContainerInterface $container): void
    {
    }

    public function events(EventManagerInterface $eventManager): EventManagerInterface
    {
        return $eventManager;
    }
}
