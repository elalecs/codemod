<?php

use CodeModTool\Commands\BatchAddTraitsCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use CodeModTool\Parser\CodeParser;
use CodeModTool\FileHandler;
use CodeModTool\Modifiers\ClassModifier;

beforeEach(function () {
    // Crear archivos de prueba
    $this->tempFile = sys_get_temp_dir() . '/TestClass_' . uniqid() . '.php';
    $classContent = <<<'PHP'
<?php

class TestClass
{
    // Clase vacía para pruebas
}
PHP;
    file_put_contents($this->tempFile, $classContent);
    
    // Crear archivo de traits temporal
    $this->tempTraitsFile = sys_get_temp_dir() . '/traits_' . uniqid() . '.txt';
    $traitsContent = <<<'TRAITS'
TestTrait1
TestTrait2
TestTrait3
TRAITS;
    file_put_contents($this->tempTraitsFile, $traitsContent);
});

afterEach(function () {
    // Limpiar archivos de prueba
    if (file_exists($this->tempFile)) {
        unlink($this->tempFile);
    }
    
    if (file_exists($this->tempFile . '.bak')) {
        unlink($this->tempFile . '.bak');
    }
    
    if (file_exists($this->tempTraitsFile)) {
        unlink($this->tempTraitsFile);
    }
});

test('añade múltiples traits desde una lista', function () {
    // Preparar dependencias
    $parser = new CodeParser();
    $fileHandler = new FileHandler();
    $modifier = new ClassModifier();
    
    // Crear y configurar aplicación
    $application = new Application();
    $application->add(new BatchAddTraitsCommand($parser, $fileHandler, $modifier));

    $command = $application->find('class:batch-add-traits');
    $commandTester = new CommandTester($command);
    
    // Ejecutar comando
    $commandTester->execute([
        'file' => $this->tempFile,
        '--traits' => 'TestTrait1,TestTrait2,TestTrait3'
    ]);

    // Verificaciones
    $output = $commandTester->getDisplay();
    expect($output)->toContain('Trait TestTrait1 añadido');
    expect($output)->toContain('Trait TestTrait2 añadido');
    expect($output)->toContain('Trait TestTrait3 añadido');
    
    $modifiedContent = file_get_contents($this->tempFile);
    expect($modifiedContent)->toContain('use TestTrait1;');
    expect($modifiedContent)->toContain('use TestTrait2;');
    expect($modifiedContent)->toContain('use TestTrait3;');
});

test('añade traits desde un archivo', function () {
    // Preparar dependencias
    $parser = new CodeParser();
    $fileHandler = new FileHandler();
    $modifier = new ClassModifier();
    
    // Crear y configurar aplicación
    $application = new Application();
    $application->add(new BatchAddTraitsCommand($parser, $fileHandler, $modifier));

    $command = $application->find('class:batch-add-traits');
    $commandTester = new CommandTester($command);
    
    // Ejecutar comando
    $commandTester->execute([
        'file' => $this->tempFile,
        '--traits-file' => $this->tempTraitsFile
    ]);

    // Verificaciones
    $output = $commandTester->getDisplay();
    expect($output)->toContain('Trait TestTrait1 añadido');
    expect($output)->toContain('Trait TestTrait2 añadido');
    expect($output)->toContain('Trait TestTrait3 añadido');
    
    $modifiedContent = file_get_contents($this->tempFile);
    expect($modifiedContent)->toContain('use TestTrait1;');
    expect($modifiedContent)->toContain('use TestTrait2;');
    expect($modifiedContent)->toContain('use TestTrait3;');
});

test('muestra cambios en modo dry-run sin aplicarlos', function () {
    // Preparar dependencias
    $parser = new CodeParser();
    $fileHandler = new FileHandler();
    $modifier = new ClassModifier();
    
    // Crear y configurar aplicación
    $application = new Application();
    $application->add(new BatchAddTraitsCommand($parser, $fileHandler, $modifier));

    $command = $application->find('class:batch-add-traits');
    $commandTester = new CommandTester($command);
    
    // Guardar contenido original
    $originalContent = file_get_contents($this->tempFile);
    
    // Ejecutar comando
    $commandTester->execute([
        'file' => $this->tempFile,
        '--traits' => 'TestTrait1,TestTrait2',
        '--dry-run' => true
    ]);

    // Verificaciones
    $output = $commandTester->getDisplay();
    expect($output)->toContain('Cambios que se realizarían');
    expect($output)->toContain('use TestTrait1;');
    expect($output)->toContain('use TestTrait2;');
    
    // Verificar que el archivo no fue modificado
    $currentContent = file_get_contents($this->tempFile);
    expect($currentContent)->toBe($originalContent);
    expect($currentContent)->not->toContain('use TestTrait1;');
    expect($currentContent)->not->toContain('use TestTrait2;');
});

test('lanza excepción cuando no se proporcionan traits', function () {
    // Preparar dependencias
    $parser = new CodeParser();
    $fileHandler = new FileHandler();
    $modifier = new ClassModifier();
    
    // Crear y configurar aplicación
    $application = new Application();
    $application->add(new BatchAddTraitsCommand($parser, $fileHandler, $modifier));

    $command = $application->find('class:batch-add-traits');
    $commandTester = new CommandTester($command);
    
    // Ejecutar comando - debe lanzar excepción
    $this->expectException(\InvalidArgumentException::class);
    
    $commandTester->execute([
        'file' => $this->tempFile
    ]);
}); 