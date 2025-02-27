<?php

use CodeModTool\Commands\BatchAddMethodsCommand;
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
    
    // Crear archivo de métodos temporal
    $this->tempMethodsFile = sys_get_temp_dir() . '/methods_' . uniqid() . '.php';
    $methodsContent = <<<'METHODS'
public function metodo1(): string
{
    return 'valor1';
}

protected function metodo2(int $param): void
{
    echo $param;
}
METHODS;
    file_put_contents($this->tempMethodsFile, $methodsContent);
    
    // Crear directorio temporal para métodos
    $this->tempMethodsDir = sys_get_temp_dir() . '/methods_dir_' . uniqid();
    mkdir($this->tempMethodsDir);
    
    // Crear archivos stub en el directorio
    file_put_contents(
        $this->tempMethodsDir . '/metodo1.php',
        <<<'METHOD1'
public function metodo1(): string
{
    return 'valor1';
}
METHOD1
    );
    
    file_put_contents(
        $this->tempMethodsDir . '/metodo2.php',
        <<<'METHOD2'
protected function metodo2(int $param): void
{
    echo $param;
}
METHOD2
    );
});

afterEach(function () {
    // Limpiar archivos de prueba
    if (file_exists($this->tempFile)) {
        unlink($this->tempFile);
    }
    
    if (file_exists($this->tempFile . '.bak')) {
        unlink($this->tempFile . '.bak');
    }
    
    if (file_exists($this->tempMethodsFile)) {
        unlink($this->tempMethodsFile);
    }
    
    if (is_dir($this->tempMethodsDir)) {
        foreach (glob($this->tempMethodsDir . '/*') as $file) {
            unlink($file);
        }
        rmdir($this->tempMethodsDir);
    }
});

test('añade múltiples métodos con formato raw PHP', function () {
    // Preparar dependencias
    $parser = new CodeParser();
    $fileHandler = new FileHandler();
    $modifier = new ClassModifier();
    
    // Crear y configurar aplicación
    $application = new Application();
    $application->add(new BatchAddMethodsCommand($parser, $fileHandler, $modifier));

    $command = $application->find('class:batch-add-methods');
    $commandTester = new CommandTester($command);
    
    $rawMethods = <<<'RAW'
public function metodo1(): string
{
    return 'valor1';
}

protected function metodo2(int $param): void
{
    echo $param;
}
RAW;
    
    // Ejecutar comando
    $commandTester->execute([
        'file' => $this->tempFile,
        '--methods' => $rawMethods
    ]);

    // Verificaciones
    $output = $commandTester->getDisplay();
    echo "Contenido del archivo de clase:\n" . file_get_contents($this->tempFile) . "\n";
    echo "Output del comando:\n" . $output . "\n";
    
    expect($output)->toContain('Método metodo1 añadido');
    expect($output)->toContain('Método metodo2 añadido');
    
    $modifiedContent = file_get_contents($this->tempFile);
    expect($modifiedContent)->toContain('public function metodo1()');
    expect($modifiedContent)->toContain('protected function metodo2(int $param)');
});

test('añade métodos desde un archivo', function () {
    // Preparar dependencias
    $parser = new CodeParser();
    $fileHandler = new FileHandler();
    $modifier = new ClassModifier();
    
    // Crear y configurar aplicación
    $application = new Application();
    $application->add(new BatchAddMethodsCommand($parser, $fileHandler, $modifier));

    $command = $application->find('class:batch-add-methods');
    $commandTester = new CommandTester($command);
    
    // Ejecutar comando
    $commandTester->execute([
        'file' => $this->tempFile,
        '--methods-file' => $this->tempMethodsFile
    ]);

    // Verificaciones
    $output = $commandTester->getDisplay();
    expect($output)->toContain('Método metodo1 añadido');
    expect($output)->toContain('Método metodo2 añadido');
    
    $modifiedContent = file_get_contents($this->tempFile);
    expect($modifiedContent)->toContain('public function metodo1()');
    expect($modifiedContent)->toContain('protected function metodo2(int $param)');
});

test('añade métodos desde un directorio', function () {
    // Preparar dependencias
    $parser = new CodeParser();
    $fileHandler = new FileHandler();
    $modifier = new ClassModifier();
    
    // Crear y configurar aplicación
    $application = new Application();
    $application->add(new BatchAddMethodsCommand($parser, $fileHandler, $modifier));

    $command = $application->find('class:batch-add-methods');
    $commandTester = new CommandTester($command);
    
    // Ejecutar comando
    $commandTester->execute([
        'file' => $this->tempFile,
        '--directory' => $this->tempMethodsDir
    ]);

    // Verificaciones
    $output = $commandTester->getDisplay();
    expect($output)->toContain('Método metodo1 añadido');
    expect($output)->toContain('Método metodo2 añadido');
    
    $modifiedContent = file_get_contents($this->tempFile);
    expect($modifiedContent)->toContain('public function metodo1()');
    expect($modifiedContent)->toContain('protected function metodo2(int $param)');
});

test('muestra cambios en modo dry-run sin aplicarlos', function () {
    // Preparar dependencias
    $parser = new CodeParser();
    $fileHandler = new FileHandler();
    $modifier = new ClassModifier();
    
    // Crear y configurar aplicación
    $application = new Application();
    $application->add(new BatchAddMethodsCommand($parser, $fileHandler, $modifier));

    $command = $application->find('class:batch-add-methods');
    $commandTester = new CommandTester($command);
    
    $rawMethods = <<<'RAW'
public function metodo1(): string
{
    return 'valor1';
}
RAW;
    
    // Guardar contenido original
    $originalContent = file_get_contents($this->tempFile);
    
    // Ejecutar comando
    $commandTester->execute([
        'file' => $this->tempFile,
        '--methods' => $rawMethods,
        '--dry-run' => true
    ]);

    // Verificaciones
    $output = $commandTester->getDisplay();
    expect($output)->toContain('Cambios que se realizarían');
    expect($output)->toContain('metodo1');
    
    // Verificar que el archivo no fue modificado
    $currentContent = file_get_contents($this->tempFile);
    expect($currentContent)->toBe($originalContent);
    expect($currentContent)->not->toContain('metodo1');
});

test('lanza excepción cuando no se proporcionan métodos', function () {
    // Preparar dependencias
    $parser = new CodeParser();
    $fileHandler = new FileHandler();
    $modifier = new ClassModifier();
    
    // Crear y configurar aplicación
    $application = new Application();
    $application->add(new BatchAddMethodsCommand($parser, $fileHandler, $modifier));

    $command = $application->find('class:batch-add-methods');
    $commandTester = new CommandTester($command);
    
    // Ejecutar comando - debe lanzar excepción
    $this->expectException(\InvalidArgumentException::class);
    
    $commandTester->execute([
        'file' => $this->tempFile
    ]);
}); 