# Contributing Guide for CodeMod Tool

## Project Architecture

### Overview

CodeMod Tool is a command-line tool designed to automatically modify PHP code by analyzing and manipulating the AST (Abstract Syntax Tree). The tool is built with PHP and distributed as an executable PHAR file.

### Directory Structure

```
codemod-tool/
├── bin/                  # Executable scripts
│   └── codemod           # Main entry point (PHP)
├── src/                  # Source code
│   ├── Commands/         # Symfony Console commands
│   ├── Modifiers/        # Code modifiers
│   ├── Parser/           # Code parsers
│   ├── CLI.php           # Main application class
│   └── FileHandler.php   # File handling utilities
├── tests/                # Tests and examples
│   ├── Feature/          # Feature tests
│   ├── Unit/             # Unit tests
│   ├── Fixtures/         # Test fixtures and auxiliary files
│   └── Pest.php          # Pest PHP testing framework configuration
├── vendor/               # Dependencies (managed by Composer)
├── box.json              # Configuration for building the PHAR
├── composer.json         # Dependencies and scripts
├── codemod.phar          # Executable PHAR file (generated)
└── stub.php              # PHAR entry point
```

## Execution Flow

### 1. Entry Point: `bin/codemod`

This file is the main entry point when running the project directly from source code. Its content is simple:

```php
#!/usr/bin/env php
<?php

require __DIR__ . '/../vendor/autoload.php';

use CodeModTool\CLI;

// Start the CLI application
$app = new CLI();
$app->run();
```

This script:
1. Loads the Composer autoloader
2. Instantiates the `CLI` class
3. Runs the `run()` method

### 2. Main Class: `src/CLI.php`

This class is the core of the application. It configures the Symfony Console application and registers the available commands:

```php
<?php

namespace CodeModTool;

use Symfony\Component\Console\Application;
use CodeModTool\Commands\ClassModifyCommand;
use CodeModTool\Commands\EnumModifyCommand;
use CodeModTool\Parser\CodeParser;
use CodeModTool\Modifiers\ClassModifier;
use CodeModTool\Modifiers\EnumModifier;

class CLI
{
    public function run(): int
    {
        $application = new Application('CodeMod Tool', '1.0.1');
        
        // Register commands
        $application->add(new ClassModifyCommand(
            new CodeParser(),
            new FileHandler(),
            new ClassModifier()
        ));
        
        $application->add(new EnumModifyCommand(
            new CodeParser(),
            new FileHandler(),
            new EnumModifier()
        ));
        
        return $application->run();
    }
}
```

### 3. Commands: `src/Commands/`

Commands implement the command-line interface using Symfony Console. The main commands are:

#### ClassModifyCommand

A unified command for all class modifications:
- Adding traits (single or from file)
- Adding properties (single or from file)
- Modifying properties
- Adding to arrays
- Adding methods (single or from file)

```php
class ClassModifyCommand extends Command
{
    // ...
    
    protected function configure()
    {
        $this->setName('class:modify')
            ->setDescription('Modify a PHP class')
            ->addArgument('file', InputArgument::REQUIRED, 'Path to class file')
            ->addOption('add-trait', null, InputOption::VALUE_REQUIRED, 'Trait to add')
            ->addOption('traits-file', null, InputOption::VALUE_REQUIRED, 'File containing traits to add')
            ->addOption('add-property', null, InputOption::VALUE_REQUIRED, 'Property to add')
            ->addOption('properties-file', null, InputOption::VALUE_REQUIRED, 'File containing properties to add')
            // ... more options
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Command implementation
    }
}
```

#### EnumModifyCommand

A command for modifying PHP enums:
- Adding cases (single or from file)
- Adding methods (single or from file)

```php
class EnumModifyCommand extends Command
{
    // ...
    
    protected function configure()
    {
        $this->setName('enum:modify')
            ->setDescription('Modify a PHP enum')
            ->addArgument('file', InputArgument::REQUIRED, 'Path to enum file')
            ->addOption('case', null, InputOption::VALUE_REQUIRED, 'Case to add')
            ->addOption('value', null, InputOption::VALUE_REQUIRED, 'Case value')
            ->addOption('cases-file', null, InputOption::VALUE_REQUIRED, 'File containing cases to add')
            ->addOption('add-method', null, InputOption::VALUE_REQUIRED, 'Method to add')
            ->addOption('methods-file', null, InputOption::VALUE_REQUIRED, 'File containing methods to add')
            // ... more options
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Command implementation
    }
}
```

### 4. Core Components

- **Parser (`src/Parser/CodeParser.php`)**: Uses `nikic/php-parser` to parse PHP code into an AST.
- **Modifiers (`src/Modifiers/`)**: Contains classes that modify the AST to make specific changes to the code.
  - `ClassModifier`: Handles modifications to PHP classes
    - Adds traits to classes (both individually and in grouped format)
    - Adds properties to classes
    - Adds methods to classes
    - Modifies existing properties
    - Adds elements to array properties
  - `EnumModifier`: Handles modifications to PHP enums
- **FileHandler (`src/FileHandler.php`)**: Handles file reading and writing, including backup creation.

## Testing Framework

### Test Structure

- **Feature Tests**: Test the commands from a user perspective
- **Unit Tests**: Test individual components in isolation
- **Fixtures**: Contains test classes, enums, and other files used by tests
- **Pest.php**: Configuration and helper functions for the Pest PHP testing framework

### Test Helpers

The `tests/Pest.php` file contains helper functions for tests:

```php
/**
 * Creates a test class file
 */
function createTestClass(string $path = null): string
{
    // Use system temp directory if no path provided
    if ($path === null) {
        $path = tempnam(sys_get_temp_dir(), 'test_class_') . '.php';
    }
    
    // Write test class content
    // ...
    
    return $path;
}

/**
 * Creates a test stub file
 */
function createTestStub(string $content, string $path = null): string
{
    // Use system temp directory if no path provided
    // ...
}

/**
 * Verifies if a class has a specific property
 */
function class_has_property(string $className, string $propertyName, ?string $type = null, ?string $visibility = null): bool
{
    // Check if the property exists in the class
    // ...
}

/**
 * Verifies if a class has a specific method
 */
function class_has_method(string $className, string $methodName, ?string $returnType = null, ?string $visibility = null): bool
{
    // Check if the method exists in the class
    // ...
}

/**
 * Verifies if a class uses a specific trait
 */
function class_uses_trait(string $className, string $traitName): bool
{
    // Check if the trait is used in the class
    // ...
}

## Estrategia de Testing

1. **Pruebas de Posicionamiento**:
   - Verificar imports dentro de namespaces
   - Chequear orden relativo entre imports y clase

2. **Pruebas de Ordenamiento**:
   - Nuevos imports después de existentes
   - Mantener orden alfabético entre grupos de imports

3. **Prevención de Duplicados**:
   - Tests que intentan añadir el mismo trait múltiples veces
   - Verificar count de imports en código resultante

**Ejemplo Test Traits**:
```php
test('adds multiple traits with namespace imports', function () {
    // Configuración comando
    $this->commandTester->execute([
        'file' => $testFile,
        '--traits' => 'Trait1,Trait2',
        '--imports' => 'App\Traits\Trait1,App\Traits\Trait2'
    ]);

    // Verificaciones
    expect($modifiedContent)->toContain('use Trait1;')
        ->and($modifiedContent)->toContain('use App\Traits\Trait1;');
});
```

## Packaging with Box (PHAR)

### What is Box?

[Box](https://github.com/box-project/box) is a tool that allows packaging PHP applications into a single executable PHAR file. PHAR (PHP Archive) is similar to a JAR file in Java, allowing distribution of an entire application as a single file.

### PHAR Build Process

1. **Configuration**: The `box.json` file defines how the PHAR will be built:

```json
{
    "output": "codemod.phar",
    "compression": "GZ",
    "directories": ["src", "vendor"],
    "stub": "stub.php",
    "force-autodiscovery": true
}
```

2. **Stub**: The `stub.php` file is the entry point of the PHAR:

```php
#!/usr/bin/env php
<?php
Phar::mapPhar('codemod.phar');
require 'phar://codemod.phar/bin/codemod';
__HALT_COMPILER(); 
```

This stub:
   - Maps the PHAR to a specific name
   - Loads the main script (`bin/codemod`)
   - Finalizes compilation with `__HALT_COMPILER()`

3. **Building**: The `composer run build` command executes:
   - `composer install --no-dev`: Installs dependencies without development ones
   - `./box.phar compile`: Builds the PHAR according to the configuration

### Relationship between `codemod.phar` and `bin/codemod`

- `bin/codemod` is the entry point when running from source code
- When building the PHAR, `bin/codemod` is included inside the PHAR file
- `stub.php` acts as a bridge, loading `bin/codemod` from inside the PHAR
- The final result (`codemod.phar`) is a standalone executable file containing the entire application

## Data Flow

1. The user runs `codemod.phar class:modify file.php --add-trait=NewTrait`
2. The stub loads `bin/codemod` from inside the PHAR
3. `bin/codemod` instantiates `CLI` and runs `run()`
4. `CLI` configures the Symfony Console application and registers commands
5. Symfony Console parses arguments and options, and executes `ClassModifyCommand`
6. `ClassModifyCommand` uses:
   - `CodeParser` to parse the PHP code
   - `ClassModifier` to modify the AST
   - `FileHandler` to read/write files
7. The modified code is saved to the original file, with a backup created

## How to Contribute

### Setting Up the Development Environment

1. Clone the repository:
   ```bash
   git clone [repository-url]
   cd codemod-tool
   ```

2. Install dependencies:
   ```bash
   composer install
   ```

3. Install Box (if not installed):
   ```bash
   bash install-box.sh
   ```

### Contribution Workflow

1. **Create a branch**: `git checkout -b feature/new-functionality`
2. **Implement changes**: Add/modify code in `src/`
3. **Test locally**:
   ```bash
   php bin/codemod [command] [options]
   ```
4. **Run tests**:
   ```bash
   vendor/bin/pest
   ```
5. **Build the PHAR**:
   ```bash
   composer run build
   ```
6. **Test the PHAR**:
   ```bash
   php codemod.phar [command] [options]
   ```
7. **Submit Pull Request**: With a clear description of the changes

### Adding New Commands

1. Create a new class in `src/Commands/` that extends `Symfony\Component\Console\Command\Command`
2. Implement the `configure()` and `execute()` methods
3. Register the command in `src/CLI.php`

### Adding New Modifiers

1. Create a new class in `src/Modifiers/`
2. Implement methods to modify the AST
3. Create a corresponding command or update an existing one to use the new modifier

### Adding Tests

1. Add feature tests in `tests/Feature/`
2. Add unit tests in `tests/Unit/`
3. Add any required test fixtures in `tests/Fixtures/`
4. Run tests using Pest: `vendor/bin/pest`

## Debugging Tips

- Use the `-vvv` option for detailed information:
  ```bash
  php codemod.phar -vvv class:modify file.php --add-trait=NewTrait
  ```

- Use dry-run mode to preview changes without applying them:
  ```bash
  php codemod.phar class:modify file.php --add-trait=NewTrait --dry-run
  ```

- Inspect the PHAR contents:
  ```bash
  php -r "print_r(new Phar('codemod.phar'));"
  ```

- Check backup files (`.bak`) after each modification

## Code Conventions

- Follow PSR-12 for code style
- Document all classes and methods with DocBlocks
- Maintain adequate test coverage
- Use return types and type declarations when possible
- Create tests for all new functionalities

## Additional Resources

- [PHP-Parser Documentation](https://github.com/nikic/PHP-Parser/blob/master/doc/0_Introduction.markdown)
- [Symfony Console Documentation](https://symfony.com/doc/current/components/console.html)
- [Box Documentation](https://github.com/box-project/box/blob/main/doc/index.md)
- [Pest PHP Documentation](https://pestphp.com/docs/installation) 