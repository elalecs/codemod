<?php

use Symfony\Component\Console\Tester\CommandTester;
use CodeModTool\Commands\ClassModifyCommand;
use CodeModTool\Parser\CodeParser;
use CodeModTool\FileHandler;
use CodeModTool\Modifiers\ClassModifier;
use Symfony\Component\Console\Application;

beforeEach(function () {
    // Crear una clase de prueba en directorio temporal
    $this->testClassPath = createTestClass();
    
    // Crear archivos para probar imports de traits en directorio temporal
    $this->traitsFilePath = tempnam(sys_get_temp_dir(), 'traits_file_') . '.txt';
    file_put_contents($this->traitsFilePath, "TestTrait1\nTestTrait2\nApp\\Traits\\TestTrait3");
    
    // Crear un archivo para propiedades en directorio temporal
    $this->propertiesFilePath = tempnam(sys_get_temp_dir(), 'properties_file_') . '.json';
    file_put_contents($this->propertiesFilePath, json_encode([
        [
            'name' => 'propFromFile1',
            'type' => 'string',
            'value' => 'value from file 1',
            'visibility' => 'private'
        ],
        [
            'name' => 'propFromFile2',
            'type' => 'array',
            'value' => ['a', 'b', 'c'],
            'visibility' => 'protected'
        ]
    ], JSON_PRETTY_PRINT));
    
    // Crear un archivo para métodos en directorio temporal
    $this->methodsFilePath = tempnam(sys_get_temp_dir(), 'methods_file_') . '.php';
    file_put_contents($this->methodsFilePath, "
    public function methodFromFile1(): string
    {
        return 'Method from file 1';
    }
    
    public function methodFromFile2(int \$param): int
    {
        return \$param * 2;
    }
    ");
    
    // Copiar archivos de métodos de Fixtures si es necesario
    $fixturesDir = __DIR__ . '/../Fixtures/';
    $this->methodStubPath = tempnam(sys_get_temp_dir(), 'method_stub_') . '.php';
    file_put_contents($this->methodStubPath, file_exists($fixturesDir . 'method_stub.php') 
        ? file_get_contents($fixturesDir . 'method_stub.php')
        : 'public function testMethod(): string { return "test"; }');
});

afterEach(function () {
    // Limpiar después del test
    cleanupTestFiles([
        $this->testClassPath,
        $this->traitsFilePath,
        $this->propertiesFilePath,
        $this->methodsFilePath,
        $this->methodStubPath
    ]);
});

// Prueba para agregar un trait
test('Case 01: class:modify agrega un trait a una clase', function () {
    $application = new Application();
    $application->add(new ClassModifyCommand(
        new CodeParser(),
        new FileHandler(),
        new ClassModifier()
    ));
    
    $command = $application->find('class:modify');
    $commandTester = new CommandTester($command);
    
    $commandTester->execute([
        'file' => $this->testClassPath,
        '--trait' => 'TestTrait'
    ]);
    
    $commandTester->assertCommandIsSuccessful();
    $output = $commandTester->getDisplay();
    
    // Verificar el mensaje de éxito
    expect($output)->toContain('Trait TestTrait agregado');
    
    // Verificar que el trait se añadió al archivo
    $classContent = file_get_contents($this->testClassPath);
    expect($classContent)->toContain('use TestTrait;');
});

// Prueba para agregar múltiples traits
test('Case 02: class:modify agrega múltiples traits con la opción --traits', function () {
    $application = new Application();
    $application->add(new ClassModifyCommand(
        new CodeParser(),
        new FileHandler(),
        new ClassModifier()
    ));
    
    $command = $application->find('class:modify');
    $commandTester = new CommandTester($command);
    
    $commandTester->execute([
        'file' => $this->testClassPath,
        '--traits' => 'Trait1,Trait2,App\\Traits\\Trait3'
    ]);
    
    $commandTester->assertCommandIsSuccessful();
    $output = $commandTester->getDisplay();
    
    // Verificar los mensajes de éxito
    expect($output)->toContain('Trait Trait1 agregado');
    expect($output)->toContain('Trait Trait2 agregado');
    expect($output)->toContain('Trait App\\Traits\\Trait3 agregado');
    expect($output)->toContain('Total de traits agregados: 3');
    
    // Verificar que los traits se añadieron al archivo
    $classContent = file_get_contents($this->testClassPath);
    expect($classContent)->toContain('use Trait1;');
    expect($classContent)->toContain('use Trait2;');
    expect($classContent)->toContain('use App\\Traits\\Trait3;');
});

// Prueba para agregar traits desde un archivo
test('Case 03: class:modify agrega traits desde un archivo', function () {
    $application = new Application();
    $application->add(new ClassModifyCommand(
        new CodeParser(),
        new FileHandler(),
        new ClassModifier()
    ));
    
    $command = $application->find('class:modify');
    $commandTester = new CommandTester($command);
    
    $commandTester->execute([
        'file' => $this->testClassPath,
        '--traits-file' => $this->traitsFilePath
    ]);
    
    $commandTester->assertCommandIsSuccessful();
    $output = $commandTester->getDisplay();
    
    // Verificar los mensajes de éxito
    expect($output)->toContain('Trait TestTrait1 agregado');
    expect($output)->toContain('Trait TestTrait2 agregado');
    expect($output)->toContain('Trait App\\Traits\\TestTrait3 agregado');
    expect($output)->toContain('Total de traits agregados: 3');
    
    // Verificar que los traits se añadieron al archivo
    $classContent = file_get_contents($this->testClassPath);
    expect($classContent)->toContain('use TestTrait1;');
    expect($classContent)->toContain('use TestTrait2;');
    expect($classContent)->toContain('use App\\Traits\\TestTrait3;');
});

// Prueba para agregar una propiedad
test('Case 04: class:modify agrega una propiedad a una clase', function () {
    $application = new Application();
    $application->add(new ClassModifyCommand(
        new CodeParser(),
        new FileHandler(),
        new ClassModifier()
    ));
    
    $command = $application->find('class:modify');
    $commandTester = new CommandTester($command);
    
    $commandTester->execute([
        'file' => $this->testClassPath,
        '--property' => 'testProp:string=Test Value'
    ]);
    
    $commandTester->assertCommandIsSuccessful();
    $output = $commandTester->getDisplay();
    
    // Verificar el mensaje de éxito
    expect($output)->toContain('Propiedad testProp añadida');
    
    // Verificar que la propiedad se añadió al archivo
    $classContent = file_get_contents($this->testClassPath);
    expect($classContent)->toContain('private string $testProp = \'Test Value\';');
});

// Prueba para agregar múltiples propiedades desde JSON
test('Case 05: class:modify agrega múltiples propiedades con la opción --properties', function () {
    $application = new Application();
    $application->add(new ClassModifyCommand(
        new CodeParser(),
        new FileHandler(),
        new ClassModifier()
    ));
    
    $command = $application->find('class:modify');
    $commandTester = new CommandTester($command);
    
    $properties = json_encode([
        [
            'name' => 'prop1',
            'type' => 'string',
            'value' => 'value1',
            'visibility' => 'private'
        ],
        [
            'name' => 'prop2',
            'type' => 'int',
            'value' => 42,
            'visibility' => 'protected'
        ]
    ]);
    
    $commandTester->execute([
        'file' => $this->testClassPath,
        '--properties' => $properties
    ]);
    
    $commandTester->assertCommandIsSuccessful();
    $output = $commandTester->getDisplay();
    
    // Verificar los mensajes de éxito
    expect($output)->toContain('Propiedad prop1 añadida');
    expect($output)->toContain('Propiedad prop2 añadida');
    expect($output)->toContain('Total de propiedades añadidas: 2');
    
    // Verificar que las propiedades se añadieron al archivo
    $classContent = file_get_contents($this->testClassPath);
    expect($classContent)->toContain('private string $prop1 = \'value1\';');
    expect($classContent)->toContain('protected int $prop2 = 42;');
});

// Prueba para agregar propiedades desde un archivo
test('Case 06: class:modify agrega propiedades desde un archivo', function () {
    // Crear archivo de propiedades
    $propertiesJson = json_encode([
        [
            'name' => 'propFromFile1',
            'value' => 'value from file 1',
            'type' => 'string',
            'visibility' => 'private'
        ],
        [
            'name' => 'propFromFile2',
            'value' => ['key1' => 'value1', 'key2' => 'value2'],
            'type' => 'array',
            'visibility' => 'protected'
        ]
    ]);
    file_put_contents($this->propertiesFilePath, $propertiesJson);
    
    $application = new Application();
    $application->add(new ClassModifyCommand(
        new CodeParser(),
        new FileHandler(),
        new ClassModifier()
    ));
    
    $command = $application->find('class:modify');
    $commandTester = new CommandTester($command);
    $commandTester->execute([
        'file' => $this->testClassPath,
        '--properties-file' => $this->propertiesFilePath
    ]);
    
    $commandTester->assertCommandIsSuccessful();
    $output = $commandTester->getDisplay();
    
    // Verificar los mensajes de éxito
    expect($output)->toContain('Propiedad propFromFile1 añadida');
    expect($output)->toContain('Propiedad propFromFile2 añadida');
    expect($output)->toContain('Total de propiedades añadidas: 2');
    
    // Verificar que las propiedades se añadieron al archivo usando la función de ayuda
    expect(class_has_property($this->testClassPath, 'propFromFile1', 'string', 'private'))->toBeTrue();
    expect(class_has_property($this->testClassPath, 'propFromFile2', 'array', 'protected'))->toBeTrue();
});

// Prueba para modificar una propiedad existente
test('Case 07: class:modify modifica una propiedad existente', function () {
    $application = new Application();
    $application->add(new ClassModifyCommand(
        new CodeParser(),
        new FileHandler(),
        new ClassModifier()
    ));
    
    $command = $application->find('class:modify');
    
    // Primero añadimos una propiedad
    $commandTester = new CommandTester($command);
    $commandTester->execute([
        'file' => $this->testClassPath,
        '--property' => 'propToModify:string=valor original'
    ]);
    
    // Verificar que la propiedad se añadió correctamente
    expect(class_has_property($this->testClassPath, 'propToModify', 'string', 'private'))->toBeTrue();
    
    // Ahora la modificamos
    $commandTester = new CommandTester($command);
    $commandTester->execute([
        'file' => $this->testClassPath,
        '--modify-property' => 'propToModify',
        '--new-value' => 'valor modificado',
        '--new-visibility' => 'protected'
    ]);
    
    $commandTester->assertCommandIsSuccessful();
    $output = $commandTester->getDisplay();
    
    // Verificar el mensaje de éxito
    expect($output)->toContain('Propiedad propToModify modificada');
    
    // Verificar que la propiedad se modificó correctamente
    expect(class_has_property($this->testClassPath, 'propToModify', 'string', 'protected'))->toBeTrue();
});

// Prueba para agregar elementos a un array
test('Case 08: class:modify agrega un elemento a una propiedad array', function () {
    $application = new Application();
    $application->add(new ClassModifyCommand(
        new CodeParser(),
        new FileHandler(),
        new ClassModifier()
    ));
    
    $command = $application->find('class:modify');
    
    // Primero añadimos una propiedad array
    $commandTester = new CommandTester($command);
    $commandTester->execute([
        'file' => $this->testClassPath,
        '--property' => 'items:array=["item1", "item2"]'
    ]);
    
    // Ahora añadimos un elemento al array
    $commandTester = new CommandTester($command);
    $commandTester->execute([
        'file' => $this->testClassPath,
        '--add-to-array' => 'items',
        '--array-value' => 'item3'
    ]);
    
    $commandTester->assertCommandIsSuccessful();
    $output = $commandTester->getDisplay();
    
    // Verificar el mensaje de éxito
    expect($output)->toContain('Elemento agregado al array items');
    
    // Verificar que el elemento se añadió al array en el archivo
    $classContent = file_get_contents($this->testClassPath);
    expect($classContent)->toContain('item3');
});

// Prueba para agregar un método
test('Case 09: class:modify agrega un método a una clase', function () {
    $application = new Application();
    $application->add(new ClassModifyCommand(
        new CodeParser(),
        new FileHandler(),
        new ClassModifier()
    ));
    
    $command = $application->find('class:modify');
    $commandTester = new CommandTester($command);
    $commandTester->execute([
        'file' => $this->testClassPath,
        '--method' => 'public function testMethod(): string
        {
            return "Test method";
        }'
    ]);
    
    $commandTester->assertCommandIsSuccessful();
    $output = $commandTester->getDisplay();
    
    // Verificar el mensaje de éxito
    expect($output)->toContain('Método testMethod agregado');
    
    // Verificar que el método se añadió correctamente
    expect(class_has_method($this->testClassPath, 'testMethod', 'string', 'public'))->toBeTrue();
});

// Prueba para agregar múltiples métodos
test('Case 10: class:modify agrega múltiples métodos con la opción --methods', function () {
    $application = new Application();
    $application->add(new ClassModifyCommand(
        new CodeParser(),
        new FileHandler(),
        new ClassModifier()
    ));
    
    $command = $application->find('class:modify');
    $commandTester = new CommandTester($command);
    
    $methodsCode = '
        public function method1(): string {
            return "Method 1";
        }
        
        protected function method2(int $param): void {
            // Method 2 implementation
        }
    ';
    
    $commandTester->execute([
        'file' => $this->testClassPath,
        '--methods' => $methodsCode
    ]);
    
    $commandTester->assertCommandIsSuccessful();
    $output = $commandTester->getDisplay();
    
    // Verificar los mensajes de éxito
    expect($output)->toContain('Método method1 agregado');
    expect($output)->toContain('Método method2 agregado');
    expect($output)->toContain('Total de métodos agregados: 2');
    
    // Verificar que los métodos se añadieron correctamente
    expect(class_has_method($this->testClassPath, 'method1', 'string', 'public'))->toBeTrue();
    expect(class_has_method($this->testClassPath, 'method2', null, 'protected'))->toBeTrue();
});

// Prueba para agregar métodos desde un archivo
test('Case 11: class:modify agrega métodos desde un archivo', function () {
    // Crear archivo de métodos
    $methodsContent = '
        public function fileMethod1(): array {
            return ["result" => "from file"];
        }
        
        protected function fileMethod2(): bool {
            return true;
        }
    ';
    file_put_contents($this->methodsFilePath, $methodsContent);
    
    $application = new Application();
    $application->add(new ClassModifyCommand(
        new CodeParser(),
        new FileHandler(),
        new ClassModifier()
    ));
    
    $command = $application->find('class:modify');
    $commandTester = new CommandTester($command);
    $commandTester->execute([
        'file' => $this->testClassPath,
        '--methods-file' => $this->methodsFilePath
    ]);
    
    $commandTester->assertCommandIsSuccessful();
    $output = $commandTester->getDisplay();
    
    // Verificar los mensajes de éxito
    expect($output)->toContain('Método fileMethod1 agregado');
    expect($output)->toContain('Método fileMethod2 agregado');
    
    // Verificar que los métodos se añadieron correctamente
    expect(class_has_method($this->testClassPath, 'fileMethod1', 'array', 'public'))->toBeTrue();
    expect(class_has_method($this->testClassPath, 'fileMethod2', 'bool', 'protected'))->toBeTrue();
});

// Prueba para combinar diferentes tipos de modificaciones
test('Case 12: class:modify permite combinar diferentes tipos de modificaciones', function () {
    $application = new Application();
    $application->add(new ClassModifyCommand(
        new CodeParser(),
        new FileHandler(),
        new ClassModifier()
    ));
    
    $command = $application->find('class:modify');
    $commandTester = new CommandTester($command);
    $commandTester->execute([
        'file' => $this->testClassPath,
        '--trait' => 'CombinedTrait',
        '--property' => 'combinedProp:string=combined value',
        '--method' => 'public function combinedMethod(): void { /* Combined method */ }'
    ]);
    
    $commandTester->assertCommandIsSuccessful();
    $output = $commandTester->getDisplay();
    
    // Verificar los mensajes de éxito
    expect($output)->toContain('Trait CombinedTrait agregado');
    expect($output)->toContain('Propiedad combinedProp añadida');
    expect($output)->toContain('Método combinedMethod agregado');
    
    // Verificar que se aplicaron todas las modificaciones
    expect(class_uses_trait($this->testClassPath, 'CombinedTrait'))->toBeTrue();
    expect(class_has_property($this->testClassPath, 'combinedProp', 'string', 'private'))->toBeTrue();
    expect(class_has_method($this->testClassPath, 'combinedMethod', 'void', 'public'))->toBeTrue();
});

// Prueba de modo dry-run
test('Case 13: class:modify en modo dry-run muestra cambios sin aplicarlos', function () {
    $application = new Application();
    $application->add(new ClassModifyCommand(
        new CodeParser(),
        new FileHandler(),
        new ClassModifier()
    ));
    
    $command = $application->find('class:modify');
    $commandTester = new CommandTester($command);
    
    // Guardar contenido original para comparación
    $originalCode = file_get_contents($this->testClassPath);
    
    $commandTester->execute([
        'file' => $this->testClassPath,
        '--trait' => 'DryRunTrait',
        '--property' => 'dryRunProp:string=Valor dry run',
        '--method' => 'public function dryRunMethod(): void { /* método dry run */ }',
        '--dry-run' => true
    ]);
    
    $commandTester->assertCommandIsSuccessful();
    $output = $commandTester->getDisplay();
    
    // Verificar los mensajes de éxito
    expect($output)->toContain('Modo dry-run: Mostrando cambios sin aplicarlos');
    expect($output)->toContain('Trait DryRunTrait agregado');
    expect($output)->toContain('Propiedad dryRunProp añadida');
    expect($output)->toContain('Método dryRunMethod agregado');
    
    // Verificar que el código no se modificó
    $codeAfter = file_get_contents($this->testClassPath);
    expect($codeAfter)->toBe($originalCode);
});

// Prueba para verificar que el modo dry-run no modifica el archivo
test('Case 14: class:modify en modo dry-run no modifica el archivo', function () {
    $application = new Application();
    $application->add(new ClassModifyCommand(
        new CodeParser(),
        new FileHandler(),
        new ClassModifier()
    ));
    
    $command = $application->find('class:modify');
    
    // Guardar el contenido original del archivo
    $originalCode = file_get_contents($this->testClassPath);
    
    $commandTester = new CommandTester($command);
    $commandTester->execute([
        'file' => $this->testClassPath,
        '--method' => 'public function dryRunMethod() { return "dry run"; }',
        '--dry-run' => true
    ]);
    
    $commandTester->assertCommandIsSuccessful();
    $output = $commandTester->getDisplay();
    
    // Verificar que muestra el mensaje de dry-run
    expect($output)->toContain('Modo dry-run');
    expect($output)->toContain('Método dryRunMethod agregado');
    
    // Verificar que el código no se modificó
    $codeAfter = file_get_contents($this->testClassPath);
    expect($codeAfter)->toBe($originalCode);
}); 