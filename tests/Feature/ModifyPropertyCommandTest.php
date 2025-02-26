<?php

use Symfony\Component\Console\Tester\CommandTester;
use CodeModTool\Commands\ModifyPropertyCommand;
use CodeModTool\Parser\CodeParser;
use CodeModTool\FileHandler;
use CodeModTool\Modifiers\ClassModifier;

beforeEach(function () {
    // Crear un archivo de clase de prueba
    createTestClass('tests/TestClassForModifyProperty.php');
});

afterEach(function () {
    // Limpiar archivos de prueba
    cleanupTestFiles(['tests/TestClassForModifyProperty.php']);
});

test('comando class:modify-property modifica una propiedad existente', function () {
    // Preparar dependencias
    $parser = new CodeParser();
    $fileHandler = new FileHandler();
    $modifier = new ClassModifier();
    
    // Crear el comando
    $command = new ModifyPropertyCommand($parser, $fileHandler, $modifier);
    
    // Crear el tester de comandos
    $commandTester = new CommandTester($command);
    
    // Ejecutar el comando
    $commandTester->execute([
        'file' => 'tests/TestClassForModifyProperty.php',
        '--name' => 'name',
        '--value' => 'Modified Test'
    ]);
    
    // Verificar que el comando se ejecutó correctamente
    expect($commandTester->getStatusCode())->toBe(0);
    
    // Verificar que la propiedad se modificó correctamente
    $classContent = file_get_contents('tests/TestClassForModifyProperty.php');
    expect($classContent)->toContain('$name = \'Modified Test\';');
});

test('comando class:modify-property modifica una propiedad y su tipo de dato', function () {
    // Preparar dependencias
    $parser = new CodeParser();
    $fileHandler = new FileHandler();
    $modifier = new ClassModifier();
    
    // Crear el comando
    $command = new ModifyPropertyCommand($parser, $fileHandler, $modifier);
    
    // Crear el tester de comandos
    $commandTester = new CommandTester($command);
    
    // Ejecutar el comando
    $commandTester->execute([
        'file' => 'tests/TestClassForModifyProperty.php',
        '--name' => 'name',
        '--value' => 'Modified Test',
        '--type' => 'string'
    ]);
    
    // Verificar que el comando se ejecutó correctamente
    expect($commandTester->getStatusCode())->toBe(0);
    
    // Verificar que la propiedad y su tipo se modificaron correctamente
    $classContent = file_get_contents('tests/TestClassForModifyProperty.php');
    expect($classContent)->toContain('string $name = \'Modified Test\';');
});

test('comando class:modify-property maneja correctamente valores numéricos con tipo string', function () {
    // Preparar dependencias
    $parser = new CodeParser();
    $fileHandler = new FileHandler();
    $modifier = new ClassModifier();
    
    // Crear el comando
    $command = new ModifyPropertyCommand($parser, $fileHandler, $modifier);
    
    // Crear el tester de comandos
    $commandTester = new CommandTester($command);
    
    // Ejecutar el comando
    $commandTester->execute([
        'file' => 'tests/TestClassForModifyProperty.php',
        '--name' => 'name',
        '--value' => '123',
        '--type' => 'string'
    ]);
    
    // Verificar que el comando se ejecutó correctamente
    expect($commandTester->getStatusCode())->toBe(0);
    
    // Verificar que la propiedad se modificó correctamente con el valor como string
    $classContent = file_get_contents('tests/TestClassForModifyProperty.php');
    expect($classContent)->toContain('string $name = \'123\';');
}); 