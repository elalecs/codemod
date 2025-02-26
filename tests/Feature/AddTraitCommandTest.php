<?php

use Symfony\Component\Console\Tester\CommandTester;
use CodeModTool\Commands\AddTraitCommand;
use CodeModTool\Parser\CodeParser;
use CodeModTool\FileHandler;
use CodeModTool\Modifiers\ClassModifier;

beforeEach(function () {
    // Crear un archivo de clase de prueba
    createTestClass('tests/TestClassForTrait.php');
    
    // Crear un archivo de trait de prueba
    file_put_contents('tests/TestTrait.php', '<?php

trait TestTrait
{
    public function traitMethod(): string
    {
        return "This is a method from TestTrait";
    }
}
');
});

afterEach(function () {
    // Limpiar archivos de prueba
    cleanupTestFiles(['tests/TestClassForTrait.php', 'tests/TestTrait.php']);
});

test('comando class:add-trait añade un trait a una clase', function () {
    // Preparar dependencias
    $parser = new CodeParser();
    $fileHandler = new FileHandler();
    $modifier = new ClassModifier();
    
    // Crear el comando
    $command = new AddTraitCommand($parser, $fileHandler, $modifier);
    
    // Crear el tester de comandos
    $commandTester = new CommandTester($command);
    
    // Ejecutar el comando
    $commandTester->execute([
        'file' => 'tests/TestClassForTrait.php',
        '--trait' => 'TestTrait'
    ]);
    
    // Verificar que el comando se ejecutó correctamente
    expect($commandTester->getStatusCode())->toBe(0);
    
    // Verificar que el trait se añadió a la clase
    $classContent = file_get_contents('tests/TestClassForTrait.php');
    expect($classContent)->toContain('use TestTrait;');
});

test('comando class:add-trait no añade un trait duplicado', function () {
    // Preparar dependencias
    $parser = new CodeParser();
    $fileHandler = new FileHandler();
    $modifier = new ClassModifier();
    
    // Crear el comando
    $command = new AddTraitCommand($parser, $fileHandler, $modifier);
    
    // Crear el tester de comandos
    $commandTester = new CommandTester($command);
    
    // Ejecutar el comando por primera vez
    $commandTester->execute([
        'file' => 'tests/TestClassForTrait.php',
        '--trait' => 'TestTrait'
    ]);
    
    // Ejecutar el comando por segunda vez
    $commandTester->execute([
        'file' => 'tests/TestClassForTrait.php',
        '--trait' => 'TestTrait'
    ]);
    
    // Verificar que el comando se ejecutó correctamente
    expect($commandTester->getStatusCode())->toBe(0);
    
    // Verificar que el trait se añadió a la clase solo una vez
    $classContent = file_get_contents('tests/TestClassForTrait.php');
    expect(substr_count($classContent, 'use TestTrait;'))->toBe(1);
}); 