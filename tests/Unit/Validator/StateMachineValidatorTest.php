<?php

namespace Tlab\Tests\Validator;

use Tlab\StateMachine\Validator\StateMachineValidator;
use PHPUnit\Framework\TestCase;

class StateMachineValidatorTest extends TestCase
{
    public function testJsonDefinitionIsValid(): void
    {
        $jsonDefinition = file_get_contents(dirname(__DIR__, 2) . '/Fixtures/sample.json');
        $errors = [];
        $validator = new StateMachineValidator();
        $isValid = $validator->validateSchema($jsonDefinition, $errors);

        self::assertTrue($isValid);
        self::assertEquals([], $errors);
    }

    public function testJsonDefinitionIsNotValid(): void
    {
        $jsonDefinition = file_get_contents(dirname(__DIR__, 2) . '/Fixtures/invalid-sample.json');
        $errors = [];
        $validator = new StateMachineValidator();
        $isValid = $validator->validateSchema($jsonDefinition, $errors);

        self::assertFalse($isValid);
        self::assertEquals([
            '/transitions/0' => 'The required properties (to) are missing'
        ], $errors);
    }

    public function testJsonDefinitionMissingState(): void
    {
        $jsonDefinition = file_get_contents(dirname(__DIR__, 2) . '/Fixtures/missing-state.json');
        $errors = [];
        $validator = new StateMachineValidator();
        $isValid = $validator->validateSchema($jsonDefinition, $errors);

        self::assertFalse($isValid);
    }

    public function testJsonDefinitionMissingEvent(): void
    {
        $jsonDefinition = file_get_contents(dirname(__DIR__, 2) . '/Fixtures/missing-event.json');
        $errors = [];
        $validator = new StateMachineValidator();
        $isValid = $validator->validateSchema($jsonDefinition, $errors);

        self::assertFalse($isValid);
    }
}
