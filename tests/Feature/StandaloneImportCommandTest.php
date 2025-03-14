<?php

use Symfony\Component\Console\Tester\CommandTester;
use CodeModTool\Commands\ClassModifyCommand;
use CodeModTool\Parser\CodeParser;
use CodeModTool\FileHandler;
use CodeModTool\Modifiers\ClassModifier;
use Symfony\Component\Console\Application;

/**
 * Test que valida que la opción --import funciona correctamente como opción independiente
 */
test('import option works as a standalone option', function () {
    // Rutas de los archivos
    $originalClassPath = __DIR__ . '/../Fixtures/SimpleImportClass.php';
    $tempClassPath = tempnam(sys_get_temp_dir(), 'import_test_') . '.php';
    
    // Verificar que el archivo original existe
    $this->assertFileExists($originalClassPath, "El archivo original de la clase no existe: $originalClassPath");
    
    // Copiar archivo original a una ubicación temporal
    copy($originalClassPath, $tempClassPath);
    
    // Verificar que el archivo temporal tenga contenido
    $tempContent = file_get_contents($tempClassPath);
    $this->assertGreaterThan(0, strlen($tempContent), "El archivo temporal está vacío");
    $this->assertStringContainsString('class SimpleImportClass', $tempContent, "No se encuentra la clase SimpleImportClass en el archivo temporal");
    
    // Crear una instancia del comando
    $parser = new CodeParser();
    $fileHandler = new FileHandler();
    $modifier = new ClassModifier();
    $command = new ClassModifyCommand($parser, $fileHandler, $modifier);
    
    // Ejecutar el comando para añadir un import
    $application = new Application();
    $application->add($command);
    $command = $application->find('class:modify');
    $commandTester = new CommandTester($command);
    
    $commandTester->execute([
        'command' => 'class:modify',
        'file' => $tempClassPath,
        '--import' => 'Illuminate\\Queue\\SerializesModel',
        '-vvv' => true
    ]);
    
    // Verificar que el comando se ejecutó correctamente
    $this->assertEquals(0, $commandTester->getStatusCode(), "El comando falló");
    
    // Verificar que la salida del comando indica que se agregó el import
    $output = $commandTester->getDisplay();
    $this->assertStringContainsString('Import Illuminate\\Queue\\SerializesModel added', $output, "No se indica que se agregó el import");
    $this->assertStringContainsString('Total of imports added: 1', $output, "No se muestra el total de imports agregados");
    
    // Leer el contenido del archivo modificado
    $modifiedContent = file_get_contents($tempClassPath);
    
    // Verificar que el import se agregó correctamente
    $this->assertStringContainsString('use Illuminate\\Queue\\SerializesModel;', $modifiedContent, "No se encuentra el import en el archivo modificado");
    
    // Limpiar archivos temporales
    @unlink($tempClassPath);
});

/**
 * Test que valida que la opción --imports funciona correctamente como opción independiente
 */
test('imports option works as a standalone option', function () {
    // Rutas de los archivos
    $originalClassPath = __DIR__ . '/../Fixtures/SimpleImportClass.php';
    $tempClassPath = tempnam(sys_get_temp_dir(), 'imports_test_') . '.php';
    
    // Verificar que el archivo original existe
    $this->assertFileExists($originalClassPath, "El archivo original de la clase no existe: $originalClassPath");
    
    // Copiar archivo original a una ubicación temporal
    copy($originalClassPath, $tempClassPath);
    
    // Crear una instancia del comando
    $parser = new CodeParser();
    $fileHandler = new FileHandler();
    $modifier = new ClassModifier();
    $command = new ClassModifyCommand($parser, $fileHandler, $modifier);
    
    // Ejecutar el comando para añadir múltiples imports
    $application = new Application();
    $application->add($command);
    $command = $application->find('class:modify');
    $commandTester = new CommandTester($command);
    
    // Ejecutar el comando con --imports
    $commandTester->execute([
        'command' => 'class:modify',
        'file' => $tempClassPath,
        '--imports' => 'Illuminate\\Support\\Facades\\Log, Illuminate\\Support\\Str',
        '-vvv' => true
    ]);
    
    // Verificar que el comando se ejecutó correctamente
    $this->assertEquals(0, $commandTester->getStatusCode(), "El comando falló");
    
    // Verificar que la salida del comando indica que se agregaron los imports
    $output = $commandTester->getDisplay();
    $this->assertStringContainsString('Import Illuminate\\Support\\Facades\\Log added', $output, "No se indica que se agregó el primer import");
    $this->assertStringContainsString('Import Illuminate\\Support\\Str added', $output, "No se indica que se agregó el segundo import");
    
    // Leer el contenido del archivo modificado
    $modifiedContent = file_get_contents($tempClassPath);
    
    // Verificar que los imports se agregaron correctamente
    $this->assertStringContainsString('use Illuminate\\Support\\Facades\\Log;', $modifiedContent, "No se encuentra el primer import en el archivo modificado");
    $this->assertStringContainsString('use Illuminate\\Support\\Str;', $modifiedContent, "No se encuentra el segundo import en el archivo modificado");
    
    // Limpiar archivos temporales
    @unlink($tempClassPath);
});

/**
 * Test que valida que la opción --imports funciona correctamente como opción independiente sin necesidad de traits
 */
test('imports option works as a standalone option without traits', function () {
    // Rutas de los archivos
    $originalClassPath = __DIR__ . '/../Fixtures/SimpleImportClass.php';
    $tempClassPath = tempnam(sys_get_temp_dir(), 'imports_test_') . '.php';
    
    // Verificar que el archivo original existe
    $this->assertFileExists($originalClassPath, "El archivo original de la clase no existe: $originalClassPath");
    
    // Copiar archivo original a una ubicación temporal
    copy($originalClassPath, $tempClassPath);
    
    // Crear una instancia del comando
    $parser = new CodeParser();
    $fileHandler = new FileHandler();
    $modifier = new ClassModifier();
    $command = new ClassModifyCommand($parser, $fileHandler, $modifier);
    
    // Ejecutar el comando para añadir múltiples imports
    $application = new Application();
    $application->add($command);
    $command = $application->find('class:modify');
    $commandTester = new CommandTester($command);
    
    // Crear un archivo temporal con imports
    $importsFile = tempnam(sys_get_temp_dir(), 'imports_file_') . '.txt';
    file_put_contents($importsFile, "Illuminate\\Database\\Eloquent\\Factories\\HasFactory\nIlluminate\\Database\\Eloquent\\SoftDeletes");
    
    // Ejecutar el comando con --imports-file
    $commandTester->execute([
        'command' => 'class:modify',
        'file' => $tempClassPath,
        '--imports-file' => $importsFile,
        '-vvv' => true
    ]);
    
    // Verificar que el comando se ejecutó correctamente
    $this->assertEquals(0, $commandTester->getStatusCode(), "El comando falló");
    
    // Verificar que la salida del comando indica que se agregaron los imports
    $output = $commandTester->getDisplay();
    $this->assertStringContainsString('Import Illuminate\\Database\\Eloquent\\Factories\\HasFactory added', $output, "No se indica que se agregó el primer import");
    $this->assertStringContainsString('Import Illuminate\\Database\\Eloquent\\SoftDeletes added', $output, "No se indica que se agregó el segundo import");
    
    // Leer el contenido del archivo modificado
    $modifiedContent = file_get_contents($tempClassPath);
    
    // Verificar que los imports se agregaron correctamente
    $this->assertStringContainsString('use Illuminate\\Database\\Eloquent\\Factories\\HasFactory;', $modifiedContent, "No se encuentra el primer import en el archivo modificado");
    $this->assertStringContainsString('use Illuminate\\Database\\Eloquent\\SoftDeletes;', $modifiedContent, "No se encuentra el segundo import en el archivo modificado");
    
    // Limpiar archivos temporales
    @unlink($tempClassPath);
    @unlink($importsFile);
});
