<?php

namespace Tlab\Tests;

use PHPUnit\Framework\TestCase;
use Tlab\StateMachine\Exceptions\ResolutionException;
use Tlab\StateMachine\Models\StateMachine;
use Tlab\StateMachine\Reader\DefinitionReader;
use Tlab\StateMachine\Resolver\CommandResolver;
use Tlab\StateMachine\Resolver\ConditionResolver;
use Tlab\StateMachine\StateMachineRunner;
use Tlab\Tests\Support\ArrayContainer;
use Tlab\Tests\Support\ConfigurableCondition;
use Tlab\Tests\Support\NotACondition;
use Tlab\Tests\Support\SampleCommand;

/**
 * validateClasses() finds every guard and command that cannot be resolved, before any of them is needed.
 */
class ClassValidationTest extends TestCase
{
    public function testAValidDefinitionPasses(): void
    {
        $runner = StateMachineRunner::fromJson((string) file_get_contents(__DIR__ . '/../Fixtures/advanced.json'));

        $runner->validateClasses();

        $this->addToAssertionCount(1); // no exception
    }

    public function testEveryUnresolvableClassIsReportedAtOnce(): void
    {
        $runner = new StateMachineRunner($this->machine(
            condition: 'App\\Missing',
            transitionCommand: NotACondition::class,
            eventCommand: SampleCommand::class,
            secondCondition: ConfigurableCondition::class,
        ));

        try {
            $runner->validateClasses();
            $this->fail('the problems should be reported');
        } catch (ResolutionException $e) {
            $errors = $e->getErrors();
            $this->assertSame(['App\\Missing', NotACondition::class, ConfigurableCondition::class], array_keys($errors));
            $this->assertStringContainsString('does not exist', $errors['App\\Missing']);
            $this->assertStringContainsString('must implement', $errors[NotACondition::class]);
            $this->assertStringContainsString('needs constructor arguments', $errors[ConfigurableCondition::class]);
            $this->assertStringContainsString("3 classes in state machine 'classes' cannot be resolved", $e->getMessage());
        }
    }

    public function testAnEventCommandIsChecked(): void
    {
        $runner = new StateMachineRunner($this->machine(eventCommand: 'App\\MissingCommand'));

        $this->expectException(ResolutionException::class);
        $this->expectExceptionMessage('App\\MissingCommand');
        $runner->validateClasses();
    }

    public function testClassesAreResolvedThroughTheContainer(): void
    {
        $container = new ArrayContainer([ConfigurableCondition::class => new ConfigurableCondition(true)]);
        $runner = new StateMachineRunner(
            $this->machine(condition: ConfigurableCondition::class),
            new ConditionResolver($container),
            new CommandResolver($container),
        );

        $runner->validateClasses();

        $this->addToAssertionCount(1); // no exception: the container supplies the constructor argument
    }

    private function machine(
        ?string $condition = null,
        ?string $transitionCommand = null,
        ?string $eventCommand = null,
        ?string $secondCondition = null,
    ): StateMachine {
        return (new DefinitionReader())->readArray([
            'name' => 'classes',
            'states' => [['name' => 'New', 'isCurrent' => true], ['name' => 'Paid'], ['name' => 'Done']],
            'transitions' => [
                ['source' => 'New', 'target' => 'Paid', 'event' => 'pay', 'condition' => $condition, 'command' => $transitionCommand],
                ['source' => 'Paid', 'target' => 'Done', 'event' => 'finish', 'condition' => $secondCondition],
            ],
            'events' => [
                ['name' => 'pay', 'command' => $eventCommand],
                ['name' => 'finish'],
            ],
        ]);
    }
}
