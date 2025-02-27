<?php

use CodeModTool\Commands\EnumModifyCommand;
use CodeModTool\FileHandler;
use CodeModTool\Modifiers\EnumModifier;
use CodeModTool\Parser\CodeParser;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

beforeEach(function() {
    // Crear copia de trabajo del enum de prueba en directorio temporal
    $fixturesDir = __DIR__ . '/../Fixtures/';
    $this->testEnumPath = tempnam(sys_get_temp_dir(), 'test_enum_') . '.php';
    file_put_contents($this->testEnumPath, file_exists($fixturesDir . 'TestEnum.php')
        ? file_get_contents($fixturesDir . 'TestEnum.php')
        : '<?php
enum TestEnum: string
{
    case CASE_ONE = "one";
    case CASE_TWO = "two";
}');
    
    // Crear un archivo para probar la opción de casos desde archivo
    $this->casesFilePath = tempnam(sys_get_temp_dir(), 'cases_file_') . '.txt';
    file_put_contents($this->casesFilePath, "CASE_FILE_A = 'value_a'\nCASE_FILE_B = 'value_b'");
    
    // Crear archivos de stub para métodos
    $this->methodStub1Path = createTestStub('<?php
public function getName(): string
{
    return $this->name;
}');
    
    $this->methodStub2Path = createTestStub('public function getValue(): string
{
    return $this->value;
}');
    
    // Crear un archivo para probar la opción de métodos desde archivo
    $this->methodsFilePath = tempnam(sys_get_temp_dir(), 'methods_file_') . '.php';
    file_put_contents($this->methodsFilePath, "
public function getLabel(): string
{
    return strtolower(\$this->name);
}

public function isDefault(): bool
{
    return \$this === self::CASE_ONE;
}
");
});

afterEach(function() {
    // Limpiar después del test
    cleanupTestFiles([
        $this->testEnumPath,
        $this->casesFilePath,
        $this->methodStub1Path,
        $this->methodStub2Path,
        $this->methodsFilePath
    ]);
});

/**
 * Comprueba si un enum tiene un caso específico
 */
function enumHasCase(string $enumClass, string $caseName): bool {
    if (!class_exists($enumClass)) {
        return false;
    }
    
    try {
        $reflection = new ReflectionClass($enumClass);
        return $reflection->hasConstant($caseName);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Comprueba si una clase tiene un método específico
 */
function classHasMethod(string $className, string $methodName): bool {
    if (!class_exists($className)) {
        return false;
    }
    
    return method_exists($className, $methodName);
}

/**
 * Carga el enum desde un archivo temporal
 */
function loadTestEnum(string $filePath): string {
    // Incluir el archivo generado para que PHP pueda encontrar la clase
    // Usamos un nombre único para evitar colisiones con otras clases ya cargadas
    $tempClassName = 'TestEnum_' . uniqid();
    
    // Leer el contenido del archivo
    $code = file_get_contents($filePath);
    
    // Verificar si el enum tiene namespace definido
    $namespacePattern = '/namespace\s+([^;]+);/';
    preg_match($namespacePattern, $code, $namespaceMatches);
    $namespace = $namespaceMatches[1] ?? null;
    
    // Modificar el código para usar el nombre de clase temporal
    $code = str_replace('TestEnum', $tempClassName, $code);
    
    // Asegurarnos de que el código tenga el namespace correcto
    if (!$namespace) {
        $code = preg_replace('/(<\?php)/', "$1\n\nnamespace Tests;", $code);
    }
    
    // Crear un archivo temporal con el nuevo nombre de clase
    $tempFile = sys_get_temp_dir() . '/' . $tempClassName . '.php';
    file_put_contents($tempFile, $code);
    
    // Cargar el archivo
    require_once $tempFile;
    
    // Devolver el nombre completo de la clase
    return $namespace ? "$namespace\\$tempClassName" : "Tests\\$tempClassName";
}

// Pruebas para casos individuales
test('Añade un caso simple a un enum', function() {
    $application = new Application();
    $application->add(new EnumModifyCommand(
        new CodeParser(),
        new FileHandler(),
        new EnumModifier()
    ));
    
    $command = $application->find('enum:modify');
    $commandTester = new CommandTester($command);
    
    $commandTester->execute([
        'file' => $this->testEnumPath,
        '--case' => 'NEW_CASE',
        '--value' => 'new_value'
    ]);
    
    $commandTester->assertCommandIsSuccessful();
    $output = $commandTester->getDisplay();
    
    // Verificar que el mensaje de éxito se muestra
    expect($output)->toContain('Caso NEW_CASE agregado');
    
    // Cargar el enum modificado y verificar que el caso existe
    $enumClassName = loadTestEnum($this->testEnumPath);
    expect(enumHasCase($enumClassName, 'NEW_CASE'))->toBeTrue();
    
    // Verificar que el valor del caso es correcto
    $caseValue = constant("$enumClassName::NEW_CASE")->value;
    expect($caseValue)->toBe('new_value');
});

// Prueba para añadir múltiples casos en un solo comando
test('Añade múltiples casos a un enum con la opción --cases', function() {
    $application = new Application();
    $application->add(new EnumModifyCommand(
        new CodeParser(),
        new FileHandler(),
        new EnumModifier()
    ));
    
    $command = $application->find('enum:modify');
    $commandTester = new CommandTester($command);
    
    $commandTester->execute([
        'file' => $this->testEnumPath,
        '--cases' => 'MULTI_A=value_a,MULTI_B=value_b'
    ]);
    
    $commandTester->assertCommandIsSuccessful();
    $output = $commandTester->getDisplay();
    
    // Verificar los mensajes de éxito
    expect($output)->toContain('Caso MULTI_A agregado');
    expect($output)->toContain('Caso MULTI_B agregado');
    expect($output)->toContain('Total de casos agregados: 2');
    
    // Cargar el enum modificado y verificar que los casos existen
    $enumClassName = loadTestEnum($this->testEnumPath);
    expect(enumHasCase($enumClassName, 'MULTI_A'))->toBeTrue();
    expect(enumHasCase($enumClassName, 'MULTI_B'))->toBeTrue();
    
    // Verificar que los valores de los casos son correctos
    expect(constant("$enumClassName::MULTI_A")->value)->toBe('value_a');
    expect(constant("$enumClassName::MULTI_B")->value)->toBe('value_b');
});

// Prueba para añadir casos desde un archivo
test('Añade casos a un enum desde un archivo', function() {
    $application = new Application();
    $application->add(new EnumModifyCommand(
        new CodeParser(),
        new FileHandler(),
        new EnumModifier()
    ));
    
    $command = $application->find('enum:modify');
    $commandTester = new CommandTester($command);
    
    $commandTester->execute([
        'file' => $this->testEnumPath,
        '--cases-file' => $this->casesFilePath
    ]);
    
    $commandTester->assertCommandIsSuccessful();
    $output = $commandTester->getDisplay();
    
    // Verificar los mensajes de éxito
    expect($output)->toContain('Caso CASE_FILE_A agregado');
    expect($output)->toContain('Caso CASE_FILE_B agregado');
    expect($output)->toContain('Total de casos agregados: 2');
    
    // Cargar el enum modificado y verificar que los casos existen
    $enumClassName = loadTestEnum($this->testEnumPath);
    expect(enumHasCase($enumClassName, 'CASE_FILE_A'))->toBeTrue();
    expect(enumHasCase($enumClassName, 'CASE_FILE_B'))->toBeTrue();
    
    // Verificar que los valores de los casos son correctos
    expect(constant("$enumClassName::CASE_FILE_A")->value)->toBe('value_a');
    expect(constant("$enumClassName::CASE_FILE_B")->value)->toBe('value_b');
});

// Pruebas para métodos individuales
test('Añade un método a un enum', function() {
    $application = new Application();
    $application->add(new EnumModifyCommand(
        new CodeParser(),
        new FileHandler(),
        new EnumModifier()
    ));
    
    $command = $application->find('enum:modify');
    $commandTester = new CommandTester($command);
    
    $methodCode = 'public function getDescription(): string { return "Description for " . $this->name; }';
    
    $commandTester->execute([
        'file' => $this->testEnumPath,
        '--method' => $methodCode
    ]);
    
    $commandTester->assertCommandIsSuccessful();
    $output = $commandTester->getDisplay();
    
    // Verificar el mensaje de éxito
    expect($output)->toContain('Método getDescription agregado');
    expect($output)->toContain('Total de métodos agregados: 1');
    
    // Cargar el enum modificado y verificar que el método existe
    $enumClassName = loadTestEnum($this->testEnumPath);
    expect(classHasMethod($enumClassName, 'getDescription'))->toBeTrue();
    
    // Verificar que el método funciona correctamente
    $instance = constant("$enumClassName::CASE_ONE");
    expect($instance->getDescription())->toBe('Description for CASE_ONE');
});

// Prueba para añadir múltiples métodos
test('Añade múltiples métodos a un enum con la opción --methods', function() {
    $application = new Application();
    $application->add(new EnumModifyCommand(
        new CodeParser(),
        new FileHandler(),
        new EnumModifier()
    ));
    
    $command = $application->find('enum:modify');
    $commandTester = new CommandTester($command);
    
    $methodsContent = "
    public function metodo1(): string {
        return 'valor1';
    }
    
    public function metodo2(int \$param): void {
        // Solo para verificar que existe
    }";
    
    $commandTester->execute([
        'file' => $this->testEnumPath,
        '--methods' => $methodsContent
    ]);
    
    $commandTester->assertCommandIsSuccessful();
    $output = $commandTester->getDisplay();
    
    // Verificar los mensajes de éxito
    expect($output)->toContain('Método metodo1 agregado');
    expect($output)->toContain('Método metodo2 agregado');
    expect($output)->toContain('Total de métodos agregados: 2');
    
    // Cargar el enum modificado y verificar que los métodos existen
    $enumClassName = loadTestEnum($this->testEnumPath);
    expect(classHasMethod($enumClassName, 'metodo1'))->toBeTrue();
    expect(classHasMethod($enumClassName, 'metodo2'))->toBeTrue();
    
    // Verificar que el primer método funciona correctamente
    $instance = constant("$enumClassName::CASE_ONE");
    expect($instance->metodo1())->toBe('valor1');
});

// Prueba para añadir métodos desde un archivo
test('Añade métodos a un enum desde un archivo', function() {
    $application = new Application();
    $application->add(new EnumModifyCommand(
        new CodeParser(),
        new FileHandler(),
        new EnumModifier()
    ));
    
    $command = $application->find('enum:modify');
    $commandTester = new CommandTester($command);
    
    $commandTester->execute([
        'file' => $this->testEnumPath,
        '--methods-file' => $this->methodsFilePath
    ]);
    
    $commandTester->assertCommandIsSuccessful();
    $output = $commandTester->getDisplay();
    
    // Verificar los mensajes de éxito
    expect($output)->toContain('Método getLabel agregado');
    expect($output)->toContain('Método isDefault agregado');
    expect($output)->toContain('Total de métodos agregados: 2');
    
    // Cargar el enum modificado y verificar que los métodos existen
    $enumClassName = loadTestEnum($this->testEnumPath);
    expect(classHasMethod($enumClassName, 'getLabel'))->toBeTrue();
    expect(classHasMethod($enumClassName, 'isDefault'))->toBeTrue();
    
    // Verificar que los métodos funcionan correctamente
    $instance = constant("$enumClassName::CASE_ONE");
    expect($instance->getLabel())->toBe('case_one');
    expect($instance->isDefault())->toBeTrue();
    
    $instance2 = constant("$enumClassName::CASE_TWO");
    expect($instance2->isDefault())->toBeFalse();
});

// Prueba para combinar adición de casos y métodos en una sola operación
test('Añade casos y métodos en una sola operación', function() {
    $application = new Application();
    $application->add(new EnumModifyCommand(
        new CodeParser(),
        new FileHandler(),
        new EnumModifier()
    ));
    
    $command = $application->find('enum:modify');
    $commandTester = new CommandTester($command);
    
    $commandTester->execute([
        'file' => $this->testEnumPath,
        '--cases' => 'COMBINED_A=value_a,COMBINED_B=value_b',
        '--method' => 'public function getCombinedName(): string { return "COMBINED_" . $this->name; }'
    ]);
    
    $commandTester->assertCommandIsSuccessful();
    $output = $commandTester->getDisplay();
    
    // Verificar los mensajes de éxito para casos
    expect($output)->toContain('Caso COMBINED_A agregado');
    expect($output)->toContain('Caso COMBINED_B agregado');
    expect($output)->toContain('Total de casos agregados: 2');
    
    // Verificar los mensajes de éxito para métodos
    expect($output)->toContain('Método getCombinedName agregado');
    expect($output)->toContain('Total de métodos agregados: 1');
    
    // Cargar el enum modificado y verificar
    $enumClassName = loadTestEnum($this->testEnumPath);
    
    // Verificar casos
    expect(enumHasCase($enumClassName, 'COMBINED_A'))->toBeTrue();
    expect(enumHasCase($enumClassName, 'COMBINED_B'))->toBeTrue();
    expect(constant("$enumClassName::COMBINED_A")->value)->toBe('value_a');
    expect(constant("$enumClassName::COMBINED_B")->value)->toBe('value_b');
    
    // Verificar método
    expect(classHasMethod($enumClassName, 'getCombinedName'))->toBeTrue();
    $instance = constant("$enumClassName::COMBINED_A");
    expect($instance->getCombinedName())->toBe('COMBINED_COMBINED_A');
});

// Prueba para modo dry-run
test('Modo dry-run muestra cambios sin aplicarlos', function() {
    $application = new Application();
    $application->add(new EnumModifyCommand(
        new CodeParser(),
        new FileHandler(),
        new EnumModifier()
    ));
    
    $command = $application->find('enum:modify');
    $commandTester = new CommandTester($command);
    
    // Guardar contenido original para comparación
    $originalCode = file_get_contents($this->testEnumPath);
    
    $commandTester->execute([
        'file' => $this->testEnumPath,
        '--case' => 'DRY_RUN_CASE',
        '--value' => 'dry_run_value',
        '--method' => 'public function dryRunMethod(): void { echo "Dry run"; }',
        '--dry-run' => true
    ]);
    
    $commandTester->assertCommandIsSuccessful();
    $output = $commandTester->getDisplay();
    
    // Verificar los mensajes de éxito
    expect($output)->toContain('Modo dry-run: Mostrando cambios sin aplicarlos');
    expect($output)->toContain('Caso DRY_RUN_CASE agregado');
    expect($output)->toContain('Método dryRunMethod agregado');
    expect($output)->toContain('[MODO DRY-RUN] Cambios NO aplicados');
    
    // Verificar que el código no se modificó
    $codeAfter = file_get_contents($this->testEnumPath);
    expect($codeAfter)->toBe($originalCode);
    
    // Cargar el enum original y verificar que el caso y método no existen
    $enumClassName = loadTestEnum($this->testEnumPath);
    expect(enumHasCase($enumClassName, 'DRY_RUN_CASE'))->toBeFalse();
    expect(classHasMethod($enumClassName, 'dryRunMethod'))->toBeFalse();
});

// Prueba de validación: debe fallar si no se proporciona ninguna opción
test('Falla si no se proporciona ninguna opción', function() {
    $application = new Application();
    $application->add(new EnumModifyCommand(
        new CodeParser(),
        new FileHandler(),
        new EnumModifier()
    ));
    
    $command = $application->find('enum:modify');
    $commandTester = new CommandTester($command);
    
    $commandTester->execute([
        'file' => $this->testEnumPath
    ]);
    
    expect($commandTester->getStatusCode())->toBe(1); // Debe fallar (código 1)
    $output = $commandTester->getDisplay();
    expect($output)->toContain('Debe proporcionar al menos una opción');
});

// Prueba de validación: debe fallar si se proporciona --case sin --value
test('Falla si se proporciona --case sin --value', function() {
    $application = new Application();
    $application->add(new EnumModifyCommand(
        new CodeParser(),
        new FileHandler(),
        new EnumModifier()
    ));
    
    $command = $application->find('enum:modify');
    $commandTester = new CommandTester($command);
    
    $commandTester->execute([
        'file' => $this->testEnumPath,
        '--case' => 'INCOMPLETE_CASE'
    ]);
    
    expect($commandTester->getStatusCode())->toBe(1); // Debe fallar (código 1)
    $output = $commandTester->getDisplay();
    expect($output)->toContain('Si se proporciona --case, también debe proporcionarse --value');
}); 