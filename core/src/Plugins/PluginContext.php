<?php

namespace PymeSec\Core\Plugins;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Route;
use PymeSec\Core\Events\Contracts\EventBusInterface;
use PymeSec\Core\Events\PublicEvent;
use PymeSec\Core\UI\Contracts\ScreenRegistryInterface;
use PymeSec\Core\UI\ScreenDefinition;

class PluginContext
{
    public function __construct(
        private readonly Application $app,
        private readonly PluginDescriptor $descriptor,
    ) {}

    public function app(): Application
    {
        return $this->app;
    }

    public function descriptor(): PluginDescriptor
    {
        return $this->descriptor;
    }

    public function manifest(): PluginManifest
    {
        return $this->descriptor->manifest();
    }

    public function id(): string
    {
        return $this->manifest()->id();
    }

    public function path(string $relativePath = ''): string
    {
        $basePath = $this->descriptor->path();

        if ($relativePath === '') {
            return $basePath;
        }

        return $basePath.'/'.ltrim($relativePath, '/');
    }

    public function loadWebRoutes(string $relativePath): void
    {
        $routePath = $this->path($relativePath);

        if (! is_file($routePath)) {
            return;
        }

        Route::middleware('web')->group($routePath);
    }

    public function loadManifestRoute(PluginRouteDefinition $route): void
    {
        $routePath = $this->path($route->file);

        if (! is_file($routePath)) {
            return;
        }

        $middleware = $route->middleware !== []
            ? $route->middleware
            : [$route->type === 'api' ? 'api' : 'web'];

        if ($route->permission !== null) {
            $middleware[] = 'core.permission:'.$route->permission;
        }

        $router = Route::middleware($middleware);

        if (is_string($route->prefix) && $route->prefix !== '') {
            $router = $router->prefix(trim($route->prefix, '/'));
        }

        $router->group($routePath);
    }

    public function registerScreen(ScreenDefinition $definition): void
    {
        $this->app->make(ScreenRegistryInterface::class)->register($definition);
    }

    /**
     * @param  Closure(PublicEvent): void  $listener
     */
    public function subscribeToEvent(string $eventName, Closure $listener): void
    {
        $this->app->make(EventBusInterface::class)->subscribe($eventName, $listener);
    }
}
