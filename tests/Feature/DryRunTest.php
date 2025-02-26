<?php

use Symfony\Component\Console\Tester\CommandTester;
use CodeModTool\Commands\AddTraitCommand;
use CodeModTool\Parser\CodeParser;
use CodeModTool\FileHandler;
use CodeModTool\Modifiers\ClassModifier;

beforeEach(function () {
    // Crear un archivo de clase de prueba
    createTestClass('tests/TestClassForDryRun.php');
});

afterEach(function () {
    // Limpiar archivos de prueba
    cleanupTestFiles(['tests/TestClassForDryRun.php']);
});

test('la opción dry-run muestra cambios sin aplicarlos', function () {
    // Preparar dependencias
    $parser = new CodeParser();
    $fileHandler = new FileHandler();
    $modifier = new ClassModifier();
    
    // Crear el comando
    $command = new AddTraitCommand($parser, $fileHandler, $modifier);
    
    // Crear el tester de comandos
    $commandTester = new CommandTester($command);
    
    // Guardar el contenido original
    $originalContent = file_get_contents('tests/TestClassForDryRun.php');
    
    // Ejecutar el comando con la opción dry-run
    $commandTester->execute([
        'file' => 'tests/TestClassForDryRun.php',
        '--trait' => 'TestTrait',
        '--dry-run' => true
    ]);
    
    // Verificar que el comando se ejecutó correctamente
    expect($commandTester->getStatusCode())->toBe(0);
    
    // Verificar que la salida contiene información sobre el modo dry-run
    $output = $commandTester->getDisplay();
    expect($output)->toContain('Ejecutando en modo dry-run');
    expect($output)->toContain('Cambios que se realizarían');
    
    // Verificar que el archivo no fue modificado
    $currentContent = file_get_contents('tests/TestClassForDryRun.php');
    expect($currentContent)->toBe($originalContent);
    expect($currentContent)->not->toContain('use TestTrait;');
    
    // Ahora ejecutar el comando sin la opción dry-run
    $commandTester->execute([
        'file' => 'tests/TestClassForDryRun.php',
        '--trait' => 'TestTrait'
    ]);
    
    // Verificar que el archivo fue modificado
    $modifiedContent = file_get_contents('tests/TestClassForDryRun.php');
    expect($modifiedContent)->not->toBe($originalContent);
    expect($modifiedContent)->toContain('use TestTrait;');
}); 