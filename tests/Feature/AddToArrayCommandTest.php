<?php

use Symfony\Component\Console\Tester\CommandTester;
use CodeModTool\Commands\AddToArrayCommand;
use CodeModTool\Parser\CodeParser;
use CodeModTool\FileHandler;
use CodeModTool\Modifiers\ClassModifier;

beforeEach(function () {
    // Crear un archivo de clase de prueba
    createTestClass('tests/TestClassForArray.php');
});

afterEach(function () {
    // Limpiar archivos de prueba
    cleanupTestFiles(['tests/TestClassForArray.php']);
});

test('comando class:add-to-array añade un elemento a un array', function () {
    // Preparar dependencias
    $parser = new CodeParser();
    $fileHandler = new FileHandler();
    $modifier = new ClassModifier();
    
    // Crear el comando
    $command = new AddToArrayCommand($parser, $fileHandler, $modifier);
    
    // Crear el tester de comandos
    $commandTester = new CommandTester($command);
    
    // Ejecutar el comando
    $commandTester->execute([
        'file' => 'tests/TestClassForArray.php',
        '--property' => 'options',
        '--key' => 'option4',
        '--value' => 'value4'
    ]);
    
    // Verificar que el comando se ejecutó correctamente
    expect($commandTester->getStatusCode())->toBe(0);
    
    // Verificar que el elemento se añadió al array
    $classContent = file_get_contents('tests/TestClassForArray.php');
    expect($classContent)->toContain('\'option4\' => \'value4\'');
});

test('comando class:add-to-array añade un valor numérico como string con la opción --string', function () {
    // Preparar dependencias
    $parser = new CodeParser();
    $fileHandler = new FileHandler();
    $modifier = new ClassModifier();
    
    // Crear el comando
    $command = new AddToArrayCommand($parser, $fileHandler, $modifier);
    
    // Crear el tester de comandos
    $commandTester = new CommandTester($command);
    
    // Ejecutar el comando
    $commandTester->execute([
        'file' => 'tests/TestClassForArray.php',
        '--property' => 'options',
        '--key' => 'option5',
        '--value' => '123',
        '--string' => true
    ]);
    
    // Verificar que el comando se ejecutó correctamente
    expect($commandTester->getStatusCode())->toBe(0);
    
    // Verificar que el elemento se añadió al array como string
    $classContent = file_get_contents('tests/TestClassForArray.php');
    expect($classContent)->toContain('\'option5\' => \'123\'');
});

test('comando class:add-to-array añade un valor numérico como entero sin la opción --string', function () {
    // Preparar dependencias
    $parser = new CodeParser();
    $fileHandler = new FileHandler();
    $modifier = new ClassModifier();
    
    // Crear el comando
    $command = new AddToArrayCommand($parser, $fileHandler, $modifier);
    
    // Crear el tester de comandos
    $commandTester = new CommandTester($command);
    
    // Ejecutar el comando
    $commandTester->execute([
        'file' => 'tests/TestClassForArray.php',
        '--property' => 'options',
        '--key' => 'option6',
        '--value' => '123'
    ]);
    
    // Verificar que el comando se ejecutó correctamente
    expect($commandTester->getStatusCode())->toBe(0);
    
    // Verificar que el elemento se añadió al array como entero
    $classContent = file_get_contents('tests/TestClassForArray.php');
    expect($classContent)->toContain('\'option6\' => 123');
}); 