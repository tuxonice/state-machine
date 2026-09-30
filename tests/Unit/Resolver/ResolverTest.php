<?php

namespace Tlab\Tests\Resolver;

use PHPUnit\Framework\TestCase;
use Tlab\StateMachine\Commands\SampleCommand;
use Tlab\StateMachine\Exceptions\ResolutionException;
use Tlab\StateMachine\Resolver\CommandResolver;
use Tlab\StateMachine\Resolver\ConditionResolver;
use Tlab\Tests\Support\ArrayContainer;
use Tlab\Tests\Support\ConfigurableCondition;
use Tlab\Tests\Support\NotACondition;
use Tlab\Tests\TestCondition;

class ResolverTest extends TestCase
{
    public function testConditionIsInstantiatedWithoutContainer(): void
    {
        $condition = (new ConditionResolver())->resolve(TestCondition::class);

        self::assertInstanceOf(TestCondition::class, $condition);
    }

    public function testConditionIsTakenFromContainerWhenItHasIt(): void
    {
        $fromContainer = new ConfigurableCondition(false);
        $resolver = new ConditionResolver(new ArrayContainer([ConfigurableCondition::class => $fromContainer]));

        self::assertSame($fromContainer, $resolver->resolve(ConfigurableCondition::class));
    }

    public function testFallsBackToInstantiationWhenContainerDoesNotHaveIt(): void
    {
        $resolver = new ConditionResolver(new ArrayContainer([]));

        self::assertInstanceOf(TestCondition::class, $resolver->resolve(TestCondition::class));
    }

    public function testMissingConditionClassThrows(): void
    {
        $this->expectException(ResolutionException::class);
        $this->expectExceptionMessage("Class 'App\\Nope' does not exist");
        (new ConditionResolver())->resolve('App\\Nope');
    }

    public function testConditionMustImplementTheInterface(): void
    {
        $this->expectException(ResolutionException::class);
        $this->expectExceptionMessage('must implement');
        (new ConditionResolver())->resolve(NotACondition::class);
    }

    public function testContainerServiceMustImplementTheInterface(): void
    {
        $resolver = new ConditionResolver(new ArrayContainer([NotACondition::class => new NotACondition()]));

        $this->expectException(ResolutionException::class);
        $resolver->resolve(NotACondition::class);
    }

    public function testCommandIsInstantiatedWithoutContainer(): void
    {
        self::assertInstanceOf(SampleCommand::class, (new CommandResolver())->resolve(SampleCommand::class));
    }

    public function testCommandMustImplementTheInterface(): void
    {
        $this->expectException(ResolutionException::class);
        (new CommandResolver())->resolve(NotACondition::class);
    }
}
