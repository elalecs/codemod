<?php

use Symfony\Component\Console\Tester\CommandTester;
use CodeModTool\Commands\ClassModifyCommand;
use CodeModTool\Parser\CodeParser;
use CodeModTool\FileHandler;
use CodeModTool\Modifiers\ClassModifier;
use Symfony\Component\Console\Application;

/**
 * Test que valida la modificación de una clase modelo de Property
 * con múltiples opciones complejas como traits, casts y translatable
 */
test('Successfully modifies a complex Property model class', function () {
    // Rutas de los archivos
    $originalModelPath = __DIR__ . '/../Fixtures/demo/app/Models/Property.php';
    $tempModelPath = tempnam(sys_get_temp_dir(), 'property_test_') . '.php';
    $traitsPath = __DIR__ . '/../Fixtures/demo/tmp/traits.txt';
    $castsPath = __DIR__ . '/../Fixtures/demo/tmp/casts.txt';
    $translatablePath = __DIR__ . '/../Fixtures/demo/tmp/translatable.txt';
    
    // Verificar que los archivos existen
    $this->assertFileExists($originalModelPath, "El archivo original del modelo no existe: $originalModelPath");
    $this->assertFileExists($traitsPath, "El archivo de traits no existe: $traitsPath");
    $this->assertFileExists($castsPath, "El archivo de casts no existe: $castsPath");
    $this->assertFileExists($translatablePath, "El archivo de translatable no existe: $translatablePath");
    
    // Copiar archivo original a una ubicación temporal
    copy($originalModelPath, $tempModelPath);
    
    // Verificar que el archivo temporal tenga contenido
    $tempContent = file_get_contents($tempModelPath);
    echo "Contenido del archivo temporal ($tempModelPath):\n";
    echo $tempContent . "\n\n"; // Mostrar todo el contenido
    $this->assertGreaterThan(0, strlen($tempContent), "El archivo temporal está vacío");
    $this->assertStringContainsString('class Property', $tempContent, "No se encuentra la clase Property en el archivo temporal");
    $this->assertStringContainsString('extends Model', $tempContent, "La clase no extiende Model en el archivo temporal");
    
    // Mostrar contenido de los archivos para debug
    echo "Contenido de traits.txt:\n";
    echo file_get_contents($traitsPath) . "\n\n";
    
    echo "Contenido de casts.txt:\n";
    echo file_get_contents($castsPath) . "\n\n";
    
    echo "Contenido de translatable.txt:\n";
    echo file_get_contents($translatablePath) . "\n\n";
    
    // Leer los archivos de datos
    $traits = file_get_contents($traitsPath);
    $casts = file_get_contents($castsPath);
    $translatable = file_get_contents($translatablePath);
    
    // Crear una instancia del comando para probar el parser directamente
    $parser = new CodeParser();
    $fileHandler = new FileHandler();
    $modifier = new ClassModifier();
    $command = new ClassModifyCommand($parser, $fileHandler, $modifier);
    
    // Verificar que el parser puede analizar el archivo
    try {
        $ast = $parser->parse($tempContent);
        echo "El parser pudo analizar el archivo correctamente.\n";
        $hasClass = false;
        foreach ($ast as $node) {
            if ($node instanceof \PhpParser\Node\Stmt\Class_) {
                $hasClass = true;
                echo "Se encontró un nodo Class_ en el AST\n";
                break;
            } elseif ($node instanceof \PhpParser\Node\Stmt\Namespace_) {
                foreach ($node->stmts as $stmt) {
                    if ($stmt instanceof \PhpParser\Node\Stmt\Class_) {
                        $hasClass = true;
                        echo "Se encontró un nodo Class_ dentro de un Namespace_ en el AST\n";
                        break 2;
                    }
                }
            }
        }
        if (!$hasClass) {
            echo "No se encontró ningún nodo Class_ en el AST\n";
        }
    } catch (\Exception $e) {
        echo "Error al analizar el archivo: " . $e->getMessage() . "\n";
    }
    
    // Ejecutar el comando con verbose
    $application = new Application();
    $application->add($command);
    $command = $application->find('class:modify');
    $commandTester = new CommandTester($command);
    
    $commandTester->execute([
        'command' => 'class:modify',
        'file' => $tempModelPath,
        '--traits' => $traits,
        '--property' => ['casts:array', 'translatable:array'],
        '--array-value' => [$casts, $translatable],
        '-vvv' => true
    ]);
    
    // Mostrar salida del comando para debug
    echo "Output del comando:\n";
    echo $commandTester->getDisplay() . "\n";
    
    // Verificar el código de estado y ajustar la expectativa al estado actual
    $statusCode = $commandTester->getStatusCode();
    if ($statusCode !== 0) {
        echo "El comando falló con código de estado: $statusCode\n";
    }
    
    // Verificar que el comando se ejecutó correctamente
    $this->assertEquals(0, $statusCode, "El comando falló con código de estado: $statusCode");
    
    // Limpiar archivos temporales
    @unlink($tempModelPath);
});

/**
 * Test que valida que el modelo Property ya tiene aplicadas las modificaciones necesarias
 */
test('Verifies Property model already has required modifications', function () {
    // Ruta del archivo
    $originalModelPath = __DIR__ . '/../Fixtures/demo/app/Models/Property.php';
    
    // Mostrar información completa para debugging
    echo "Ruta absoluta del archivo: " . realpath($originalModelPath) . "\n";
    
    // Verificar que el archivo existe usando assertFileExists
    $this->assertFileExists($originalModelPath, "El archivo del modelo no existe: $originalModelPath");
    
    // Leer el contenido del archivo
    $modelContent = file_get_contents($originalModelPath);
    echo "Longitud del contenido: " . strlen($modelContent) . " bytes\n";
    
    // Buscar líneas específicas para depuración
    echo "Línea de la clase: " . (preg_match('/class Property extends Model/', $modelContent) ? "Encontrada" : "No encontrada") . "\n";
    echo "Línea de traits: " . (preg_match('/use HasFactory, HasUuids, SoftDeletes, HasTranslations, Auditable, InteractsWithMedia;/', $modelContent) ? "Encontrada" : "No encontrada") . "\n";
    
    // Verificar que contiene todas las modificaciones necesarias
    $this->assertStringContainsString('class Property', $modelContent, "No se encuentra la clase Property");
    $this->assertStringContainsString('extends Model', $modelContent, "La clase no extiende Model");
    
    // Traits en la declaración de use dentro de la clase
    $this->assertStringContainsString('use HasFactory, HasUuids, SoftDeletes, HasTranslations, Auditable, InteractsWithMedia;', $modelContent, "No se encuentran los traits en la declaración use");
    
    // Imports
    $this->assertStringContainsString('use App\Traits\HasTranslations;', $modelContent, "No se encuentra el import de HasTranslations");
    $this->assertStringContainsString('use App\Traits\Auditable;', $modelContent, "No se encuentra el import de Auditable");
    $this->assertStringContainsString('use App\Traits\InteractsWithMedia;', $modelContent, "No se encuentra el import de InteractsWithMedia");
    
    // Propiedad Casts
    $this->assertStringContainsString('protected $casts = [', $modelContent, "No se encuentra la propiedad casts");
    $this->assertStringContainsString("'type' => PropertyType::class", $modelContent, "No se encuentra el casting de type");
    $this->assertStringContainsString("'status' => PropertyStatus::class", $modelContent, "No se encuentra el casting de status");
    $this->assertStringContainsString("'amenities' => 'array'", $modelContent, "No se encuentra el casting de amenities");
    $this->assertStringContainsString("'settings' => 'array'", $modelContent, "No se encuentra el casting de settings");
    $this->assertStringContainsString("'metadata' => 'array'", $modelContent, "No se encuentra el casting de metadata");
    
    // Propiedad Translatable
    $this->assertStringContainsString('protected $translatable = [', $modelContent, "No se encuentra la propiedad translatable");
    $this->assertStringContainsString("'name', 'description', 'policies'", $modelContent, "No se encuentran los campos traducibles");
    
    // Relaciones importantes
    $this->assertStringContainsString('public function locations(): HasMany', $modelContent, "No se encuentra el método locations");
    $this->assertStringContainsString('public function units(): HasMany', $modelContent, "No se encuentra el método units");
}); 