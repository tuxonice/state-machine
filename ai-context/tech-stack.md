# State Machine Package - Technical Stack

## Overview
This is a PHP package for managing state machines in a flexible and framework-agnostic way. It allows defining complex workflows using JSON configuration and provides a runtime for executing state transitions based on defined rules.

## Core Technologies

### Programming Language
- **PHP 8.2+**: The package is built using modern PHP features and requires PHP 8.2 or higher.

### Dependencies

#### Main Dependencies
- **jbzoo/mermaid-php**: Used for generating Mermaid.js compatible diagrams for visualizing state machines.
- **opis/json-schema**: Provides JSON Schema validation for the state machine configuration files.

#### Development Dependencies
- **PHPUnit**: For unit testing the package.
- **PHP_CodeSniffer**: For enforcing coding standards.
- **PHPStan**: For static code analysis.
- **Symfony VarDumper**: For debugging and development purposes.

## Project Structure

```
src/
├── Commands/       # Command pattern implementations
├── Conditions/     # Condition classes for transition guards
├── Exceptions/     # Custom exception classes
├── Flowchart/      # Mermaid.js diagram generation
├── Machines/       # Core state machine implementation
├── Models/         # Data models
├── Reader/         # Configuration file readers
└── Validator/      # Configuration validation
```

## Key Features

1. **JSON Configuration**: Define state machines using a simple JSON format.
2. **State Management**: Track and manage states throughout an object's lifecycle.
3. **Transition Rules**: Define allowed state transitions with conditions.
4. **Visualization**: Generate Mermaid.js diagrams for state machine visualization.
5. **Validation**: Built-in validation of state machine configurations.
6. **Framework Agnostic**: Can be integrated with any PHP project.

## Development Tools

### Testing
- **PHPUnit**: Comprehensive test suite with unit tests.
- **PHP_CodeSniffer**: Enforces PSR-12 coding standards.
- **PHPStan**: Static analysis for catching potential issues.

### Build & Quality
- **Composer**: Dependency management.
- **Docker**: Containerized development environment.
- **Makefile**: Common development tasks automation.

## Integration

This package can be integrated into any PHP project using Composer:

```bash
composer require tuxonice/state-machine
```

## Documentation

For detailed usage instructions and examples, please refer to the main [README.md](../README.md) file.

## License

This project is open-source and available under the MIT License.
