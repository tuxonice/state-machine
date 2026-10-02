# Symfony

The package has no Symfony dependency. Symfony's service locators implement PSR-11, so tagged services can be handed
to the resolvers as they are. Classes the locator does not know are still instantiated with `new`.

## Services

Tag your conditions and commands, and build one runner per definition:

```yaml
# config/services.yaml
services:
    _instanceof:
        Tlab\StateMachine\Conditions\ConditionInterface:
            tags: ['app.state_machine_handler']
        Tlab\StateMachine\Commands\CommandInterface:
            tags: ['app.state_machine_handler']

    Tlab\StateMachine\Reader\DefinitionReader: ~

    Tlab\StateMachine\Resolver\ConditionResolver:
        arguments: [!tagged_locator { tag: 'app.state_machine_handler' }]

    Tlab\StateMachine\Resolver\CommandResolver:
        arguments: [!tagged_locator { tag: 'app.state_machine_handler' }]

    app.order_state_machine:
        class: Tlab\StateMachine\StateMachineRunner
        arguments:
            - '@=service("Tlab\\StateMachine\\Reader\\DefinitionReader").readFile(parameter("kernel.project_dir") ~ "/config/state_machines/order.json")'
            - '@Tlab\StateMachine\Resolver\ConditionResolver'
            - '@Tlab\StateMachine\Resolver\CommandResolver'
            - '@event_dispatcher'
```

Tagged services are indexed by their class name, which is what the definition files contain.

## Events

`@event_dispatcher` implements PSR-14, so `BeforeTransition`, `AfterTransition` and `TransitionBlocked` can be
handled with ordinary listeners or `#[AsEventListener]`.

## Doctrine entities

Implement `StatefulInterface` on the entity. `applyTo()` only changes the object, so flush the entity manager
afterwards, or pass a `StateStorageInterface` that persists on write.

## Timeouts

Implement `TimeoutScheduler` on a table or on Messenger's delayed messages, and call `processTimeouts()` from a
console command run by cron, or from a scheduled message in the Scheduler component.

> This recipe is documentation only. It has not been run in a Symfony application.
