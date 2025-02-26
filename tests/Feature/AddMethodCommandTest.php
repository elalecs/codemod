<?php

use Symfony\Component\Console\Tester\CommandTester;
use CodeModTool\Commands\AddMethodCommand;
use CodeModTool\Parser\CodeParser;
use CodeModTool\FileHandler;
use CodeModTool\Modifiers\ClassModifier;

beforeEach(function () {
    // Crear un archivo de clase de prueba
    createTestClass('tests/TestClassForMethod.php');
    
    // Crear un archivo de stub con etiqueta PHP
    createTestStub('<?php
public function getDescription(): string
{
    return $this->description;
}', 'tests/method_stub_with_php.php');
    
    // Crear un archivo de stub sin etiqueta PHP
    createTestStub('public function getOptions(): array
{
    return $this->options;
}', 'tests/method_stub_without_php.php');
});

afterEach(function () {
    // Limpiar archivos de prueba
    cleanupTestFiles([
        'tests/TestClassForMethod.php',
        'tests/method_stub_with_php.php',
        'tests/method_stub_without_php.php'
    ]);
});

test('comando class:add-method añade un método desde un stub con etiqueta PHP', function () {
    // Preparar dependencias
    $parser = new CodeParser();
    $fileHandler = new FileHandler();
    $modifier = new ClassModifier();
    
    // Crear el comando
    $command = new AddMethodCommand($parser, $fileHandler, $modifier);
    
    // Crear el tester de comandos
    $commandTester = new CommandTester($command);
    
    // Ejecutar el comando
    $commandTester->execute([
        'file' => 'tests/TestClassForMethod.php',
        '--stub' => 'tests/method_stub_with_php.php'
    ]);
    
    // Verificar que el comando se ejecutó correctamente
    expect($commandTester->getStatusCode())->toBe(0);
    
    // Verificar que el método se añadió a la clase
    $classContent = file_get_contents('tests/TestClassForMethod.php');
    expect($classContent)->toContain('public function getDescription()');
    expect($classContent)->toContain('return $this->description;');
});

test('comando class:add-method añade un método desde un stub sin etiqueta PHP', function () {
    // Preparar dependencias
    $parser = new CodeParser();
    $fileHandler = new FileHandler();
    $modifier = new ClassModifier();
    
    // Crear el comando
    $command = new AddMethodCommand($parser, $fileHandler, $modifier);
    
    // Crear el tester de comandos
    $commandTester = new CommandTester($command);
    
    // Ejecutar el comando
    $commandTester->execute([
        'file' => 'tests/TestClassForMethod.php',
        '--stub' => 'tests/method_stub_without_php.php'
    ]);
    
    // Verificar que el comando se ejecutó correctamente
    expect($commandTester->getStatusCode())->toBe(0);
    
    // Verificar que el método se añadió a la clase
    $classContent = file_get_contents('tests/TestClassForMethod.php');
    expect($classContent)->toContain('public function getOptions()');
    expect($classContent)->toContain('return $this->options;');
});

test('comando class:add-method añade un método directamente desde la opción --method', function () {
    // Preparar dependencias
    $parser = new CodeParser();
    $fileHandler = new FileHandler();
    $modifier = new ClassModifier();
    
    // Crear el comando
    $command = new AddMethodCommand($parser, $fileHandler, $modifier);
    
    // Crear el tester de comandos
    $commandTester = new CommandTester($command);
    
    // Ejecutar el comando
    $commandTester->execute([
        'file' => 'tests/TestClassForMethod.php',
        '--method' => 'public function testMethod(): bool { return true; }'
    ]);
    
    // Verificar que el comando se ejecutó correctamente
    expect($commandTester->getStatusCode())->toBe(0);
    
    // Verificar que el método se añadió a la clase
    $classContent = file_get_contents('tests/TestClassForMethod.php');
    expect($classContent)->toContain('public function testMethod()');
    expect($classContent)->toContain('return true;');
}); 