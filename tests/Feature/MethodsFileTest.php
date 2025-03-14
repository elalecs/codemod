<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class MethodsFileTest extends TestCase
{
    private string $tempFile;
    private string $methodsFile;
    private string $backupFile;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Crear un archivo temporal para la prueba
        $this->tempFile = __DIR__ . '/../temp/TestClassForMethodsFile.php';
        $this->backupFile = $this->tempFile . '.bak';
        $this->methodsFile = __DIR__ . '/../Fixtures/middleware_methods.php';
        
        // Asegurarse de que el directorio temp existe
        if (!is_dir(dirname($this->tempFile))) {
            mkdir(dirname($this->tempFile), 0777, true);
        }
        
        // Crear un archivo de clase de prueba
        file_put_contents($this->tempFile, '<?php

namespace App\Tests;

class TestClassForMethodsFile
{
    protected $properties = [];
    
    public function existingMethod()
    {
        return "This is an existing method";
    }
}');
    }

    protected function tearDown(): void
    {
        // Limpiar archivos temporales
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
        
        if (file_exists($this->backupFile)) {
            unlink($this->backupFile);
        }
        
        parent::tearDown();
    }

    public function testMethodsFileOption()
    {
        // Ejecutar el comando codemod con la opción --methods-file
        $process = new Process([
            './codemod.phar',
            'class:modify',
            $this->tempFile,
            '--methods-file=' . $this->methodsFile
        ]);
        
        $process->run();
        
        // Verificar que el comando se ejecutó correctamente
        $this->assertEquals(0, $process->getExitCode(), 'El comando falló: ' . $process->getErrorOutput());
        
        // Verificar que el mensaje de éxito contiene la información esperada
        $output = $process->getOutput();
        $this->assertStringContainsString('Method handle added from file', $output);
        $this->assertStringContainsString('Total of methods added: 1', $output);
        
        // Verificar que el método se agregó al archivo
        $modifiedContent = file_get_contents($this->tempFile);
        $this->assertStringContainsString('public function handle($request, \Closure $next)', $modifiedContent);
        $this->assertStringContainsString('abort(403, \'No tienes permiso para acceder a esta propiedad.\');', $modifiedContent);
    }

    public function testMethodsFileWithExistingMethod()
    {
        // Primero agregar el método
        $process = new Process([
            './codemod.phar',
            'class:modify',
            $this->tempFile,
            '--methods-file=' . $this->methodsFile
        ]);
        
        $process->run();
        
        // Ahora intentar agregar el mismo método nuevamente
        $process = new Process([
            './codemod.phar',
            'class:modify',
            $this->tempFile,
            '--methods-file=' . $this->methodsFile
        ]);
        
        $process->run();
        
        // Verificar que el comando se ejecutó correctamente pero no se agregó el método
        $this->assertEquals(0, $process->getExitCode(), 'El comando falló: ' . $process->getErrorOutput());
        
        // Verificar que el mensaje indica que no se realizaron cambios
        $output = $process->getOutput();
        $this->assertStringContainsString('No methods were added from file', $output);
        $this->assertStringContainsString('No changes were made to the class', $output);
    }
}
