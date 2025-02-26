<?php

use Symfony\Component\Console\Tester\CommandTester;
use CodeModTool\Commands\AddPropertyCommand;
use CodeModTool\Parser\CodeParser;
use CodeModTool\FileHandler;
use CodeModTool\Modifiers\ClassModifier;

beforeEach(function () {
    // Crear un archivo de clase de prueba
    createTestClass('tests/TestClassForProperty.php');
});

afterEach(function () {
    // Limpiar archivos de prueba
    cleanupTestFiles(['tests/TestClassForProperty.php']);
});

test('comando class:add-property añade una propiedad a una clase', function () {
    // Preparar dependencias
    $parser = new CodeParser();
    $fileHandler = new FileHandler();
    $modifier = new ClassModifier();
    
    // Crear el comando
    $command = new AddPropertyCommand($parser, $fileHandler, $modifier);
    
    // Crear el tester de comandos
    $commandTester = new CommandTester($command);
    
    // Ejecutar el comando
    $commandTester->execute([
        'file' => 'tests/TestClassForProperty.php',
        '--name' => 'testProperty',
        '--value' => 'test value',
        '--visibility' => 'public'
    ]);
    
    // Verificar que el comando se ejecutó correctamente
    expect($commandTester->getStatusCode())->toBe(0);
    
    // Verificar que la propiedad se añadió a la clase
    $classContent = file_get_contents('tests/TestClassForProperty.php');
    expect($classContent)->toContain('public $testProperty = \'test value\';');
});

test('comando class:add-property añade una propiedad con tipo de dato', function () {
    // Preparar dependencias
    $parser = new CodeParser();
    $fileHandler = new FileHandler();
    $modifier = new ClassModifier();
    
    // Crear el comando
    $command = new AddPropertyCommand($parser, $fileHandler, $modifier);
    
    // Crear el tester de comandos
    $commandTester = new CommandTester($command);
    
    // Ejecutar el comando
    $commandTester->execute([
        'file' => 'tests/TestClassForProperty.php',
        '--name' => 'apiVersion',
        '--value' => '2.0',
        '--visibility' => 'public',
        '--type' => 'string'
    ]);
    
    // Verificar que el comando se ejecutó correctamente
    expect($commandTester->getStatusCode())->toBe(0);
    
    // Verificar que la propiedad se añadió a la clase con el tipo de dato
    $classContent = file_get_contents('tests/TestClassForProperty.php');
    expect($classContent)->toContain('public string $apiVersion = \'2.0\';');
});

test('comando class:add-property maneja correctamente valores numéricos con tipo string', function () {
    // Preparar dependencias
    $parser = new CodeParser();
    $fileHandler = new FileHandler();
    $modifier = new ClassModifier();
    
    // Crear el comando
    $command = new AddPropertyCommand($parser, $fileHandler, $modifier);
    
    // Crear el tester de comandos
    $commandTester = new CommandTester($command);
    
    // Ejecutar el comando
    $commandTester->execute([
        'file' => 'tests/TestClassForProperty.php',
        '--name' => 'code',
        '--value' => '123',
        '--visibility' => 'public',
        '--type' => 'string'
    ]);
    
    // Verificar que el comando se ejecutó correctamente
    expect($commandTester->getStatusCode())->toBe(0);
    
    // Verificar que la propiedad se añadió a la clase con el valor como string
    $classContent = file_get_contents('tests/TestClassForProperty.php');
    expect($classContent)->toContain('public string $code = \'123\';');
}); 