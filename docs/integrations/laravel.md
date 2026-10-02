# Laravel

The package has no Laravel dependency. It needs two things from the framework: a PSR-11 container, so conditions and
commands receive their dependencies, and somewhere to keep the runner.

Laravel's container implements PSR-11, but `has()` only answers `true` for classes that were bound explicitly.
Passing it directly would make the resolvers fall back to `new` for every unbound condition or command, with no
dependencies injected. The small adapter below makes `has()` true for any class.

```php
<?php

namespace App\Providers;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;
use Psr\Container\ContainerInterface;
use Tlab\StateMachine\Reader\DefinitionReader;
use Tlab\StateMachine\Registry\StateMachineRegistry;
use Tlab\StateMachine\Resolver\CommandResolver;
use Tlab\StateMachine\Resolver\ConditionResolver;
use Tlab\StateMachine\StateMachineRunner;

class StateMachineServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(StateMachineRegistry::class, function (Container $app) {
            $container = new class ($app) implements ContainerInterface {
                public function __construct(private Container $app)
                {
                }

                public function has(string $id): bool
                {
                    return class_exists($id) || $this->app->bound($id);
                }

                public function get(string $id): mixed
                {
                    return $this->app->make($id);
                }
            };

            $reader = new DefinitionReader();
            $registry = new StateMachineRegistry();

            foreach (glob(base_path('state-machines/*.json')) ?: [] as $file) {
                $registry->register(new StateMachineRunner(
                    $reader->readFile($file),
                    new ConditionResolver($container),
                    new CommandResolver($container),
                ));
            }

            return $registry;
        });
    }
}
```

Then use it anywhere:

```php
app(StateMachineRegistry::class)->get('order')->applyTo($order, 'pay');
```

## Eloquent models

Implement `StatefulInterface` on the model, mapping it to a column:

```php
class Order extends Model implements StatefulInterface
{
    public function getState(): ?string { return $this->status; }
    public function setState(string $state): void { $this->status = $state; }
}
```

`applyTo()` only sets the attribute. To save it, pass a `StateStorageInterface` that calls `$subject->save()`
after writing.

## Events

Pass a dispatcher as the `dispatcher` argument of the runner to be notified of transitions. Laravel's dispatcher does not implement PSR-14. Bridge it with a few lines, or register your listeners on a
PSR-14 dispatcher of your choice (for example `league/event`) and pass that to the runner:

```php
$dispatcher = new class ($app['events']) implements \Psr\EventDispatcher\EventDispatcherInterface {
    public function __construct(private \Illuminate\Contracts\Events\Dispatcher $events)
    {
    }

    public function dispatch(object $event): object
    {
        $this->events->dispatch($event);

        return $event;
    }
};
```

## Timeouts

Implement `TimeoutScheduler` on a table and call `processTimeouts()` from a scheduled command:

```php
$schedule->call(fn () => app(StateMachineRegistry::class)->get('order')->processTimeouts())->everyMinute();
```

> This recipe is documentation only. It has not been run against a Laravel application.
