<?php

use CodeModTool\Commands\BatchAddPropertiesCommand;
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
    
    // Crear archivo de propiedades temporal
    $this->tempPropertiesFile = sys_get_temp_dir() . '/properties_' . uniqid() . '.json';
    $propertiesContent = json_encode([
        ['name' => 'prop1', 'value' => "'valor1'", 'visibility' => 'private', 'type' => 'string'],
        ['name' => 'prop2', 'value' => '0', 'visibility' => 'protected', 'type' => 'int'],
        ['name' => 'prop3', 'value' => '[]', 'visibility' => 'public', 'type' => 'array']
    ]);
    file_put_contents($this->tempPropertiesFile, $propertiesContent);
});

afterEach(function () {
    // Limpiar archivos de prueba
    if (file_exists($this->tempFile)) {
        unlink($this->tempFile);
    }
    
    if (file_exists($this->tempFile . '.bak')) {
        unlink($this->tempFile . '.bak');
    }
    
    if (file_exists($this->tempPropertiesFile)) {
        unlink($this->tempPropertiesFile);
    }
});

test('añade múltiples propiedades con formato JSON', function () {
    // Preparar dependencias
    $parser = new CodeParser();
    $fileHandler = new FileHandler();
    $modifier = new ClassModifier();
    
    // Crear y configurar aplicación
    $application = new Application();
    $application->add(new BatchAddPropertiesCommand($parser, $fileHandler, $modifier));

    $command = $application->find('class:batch-add-properties');
    $commandTester = new CommandTester($command);
    
    $jsonProperties = json_encode([
        ['name' => 'prop1', 'value' => "'valor1'", 'visibility' => 'private', 'type' => 'string'],
        ['name' => 'prop2', 'value' => '0', 'visibility' => 'protected', 'type' => 'int'],
        ['name' => 'prop3', 'value' => '[]', 'visibility' => 'public', 'type' => 'array']
    ]);
    
    // Ejecutar comando
    $commandTester->execute([
        'file' => $this->tempFile,
        '--properties' => $jsonProperties
    ]);

    // Verificaciones
    $output = $commandTester->getDisplay();
    echo "Contenido del archivo de clase:\n" . file_get_contents($this->tempFile) . "\n";
    echo "Output del comando:\n" . $output . "\n";

    expect($output)->toContain('Propiedad prop1 añadida');
    expect($output)->toContain('Propiedad prop2 añadida');
    expect($output)->toContain('Propiedad prop3 añadida');
    
    $modifiedContent = file_get_contents($this->tempFile);
    expect($modifiedContent)->toContain('private string $prop1');
    expect($modifiedContent)->toContain('protected int $prop2');
    expect($modifiedContent)->toContain('public array $prop3');
});

test('añade múltiples propiedades con formato raw PHP', function () {
    // Preparar dependencias
    $parser = new CodeParser();
    $fileHandler = new FileHandler();
    $modifier = new ClassModifier();
    
    // Crear y configurar aplicación
    $application = new Application();
    $application->add(new BatchAddPropertiesCommand($parser, $fileHandler, $modifier));

    $command = $application->find('class:batch-add-properties');
    $commandTester = new CommandTester($command);
    
    $rawProperties = <<<'RAW'
private string $prop1 = 'valor1';
protected int $prop2 = 0;
public array $prop3 = [];
RAW;
    
    // Ejecutar comando
    $commandTester->execute([
        'file' => $this->tempFile,
        '--properties-raw' => $rawProperties
    ]);

    // Verificaciones
    $output = $commandTester->getDisplay();
    echo "Contenido del archivo de clase:\n" . file_get_contents($this->tempFile) . "\n";
    echo "Output del comando:\n" . $output . "\n";

    expect($output)->toContain('Propiedad prop1 añadida');
    expect($output)->toContain('Propiedad prop2 añadida');
    expect($output)->toContain('Propiedad prop3 añadida');
    
    $modifiedContent = file_get_contents($this->tempFile);
    expect($modifiedContent)->toContain('private string $prop1');
    expect($modifiedContent)->toContain('protected int $prop2');
    expect($modifiedContent)->toContain('public array $prop3');
});

test('añade propiedades desde un archivo', function () {
    // Preparar dependencias
    $parser = new CodeParser();
    $fileHandler = new FileHandler();
    $modifier = new ClassModifier();
    
    // Crear y configurar aplicación
    $application = new Application();
    $application->add(new BatchAddPropertiesCommand($parser, $fileHandler, $modifier));

    $command = $application->find('class:batch-add-properties');
    $commandTester = new CommandTester($command);
    
    // Ejecutar comando
    $commandTester->execute([
        'file' => $this->tempFile,
        '--properties-file' => $this->tempPropertiesFile
    ]);

    // Verificaciones
    $output = $commandTester->getDisplay();
    echo "Contenido del archivo de clase:\n" . file_get_contents($this->tempFile) . "\n";
    echo "Output del comando:\n" . $output . "\n";

    expect($output)->toContain('Propiedad prop1 añadida');
    expect($output)->toContain('Propiedad prop2 añadida');
    expect($output)->toContain('Propiedad prop3 añadida');
    
    $modifiedContent = file_get_contents($this->tempFile);
    expect($modifiedContent)->toContain('private string $prop1');
    expect($modifiedContent)->toContain('protected int $prop2');
    expect($modifiedContent)->toContain('public array $prop3');
});

test('muestra cambios en modo dry-run sin aplicarlos', function () {
    // Preparar dependencias
    $parser = new CodeParser();
    $fileHandler = new FileHandler();
    $modifier = new ClassModifier();
    
    // Crear y configurar aplicación
    $application = new Application();
    $application->add(new BatchAddPropertiesCommand($parser, $fileHandler, $modifier));

    $command = $application->find('class:batch-add-properties');
    $commandTester = new CommandTester($command);
    
    $rawProperties = <<<'RAW'
private string $prop1 = 'valor1';
protected int $prop2 = 0;
RAW;
    
    // Guardar contenido original
    $originalContent = file_get_contents($this->tempFile);
    
    // Ejecutar comando
    $commandTester->execute([
        'file' => $this->tempFile,
        '--properties-raw' => $rawProperties,
        '--dry-run' => true
    ]);

    // Verificaciones
    $output = $commandTester->getDisplay();
    echo "Contenido del archivo de clase:\n" . file_get_contents($this->tempFile) . "\n";
    echo "Output del comando:\n" . $output . "\n";

    expect($output)->toContain('Cambios que se realizarían');
    expect($output)->toContain('$prop1');
    expect($output)->toContain('$prop2');
    
    // Verificar que el archivo no fue modificado
    $currentContent = file_get_contents($this->tempFile);
    expect($currentContent)->toBe($originalContent);
    expect($currentContent)->not->toContain('$prop1');
    expect($currentContent)->not->toContain('$prop2');
});

test('lanza excepción cuando no se proporcionan propiedades', function () {
    // Preparar dependencias
    $parser = new CodeParser();
    $fileHandler = new FileHandler();
    $modifier = new ClassModifier();
    
    // Crear y configurar aplicación
    $application = new Application();
    $application->add(new BatchAddPropertiesCommand($parser, $fileHandler, $modifier));

    $command = $application->find('class:batch-add-properties');
    $commandTester = new CommandTester($command);
    
    // Ejecutar comando - debe lanzar excepción
    $this->expectException(\InvalidArgumentException::class);
    
    $commandTester->execute([
        'file' => $this->tempFile
    ]);
}); 