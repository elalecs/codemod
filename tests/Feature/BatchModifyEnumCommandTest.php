<?php

use CodeModTool\Commands\BatchModifyEnumCommand;
use CodeModTool\FileHandler;
use CodeModTool\Modifiers\EnumModifier;
use CodeModTool\Parser\CodeParser;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

beforeEach(function() {
    // Crear copia de trabajo para el test
    copy(__DIR__ . '/../TestEnum.php', __DIR__ . '/../TestEnum.php.test');
});

afterEach(function() {
    // Limpiar después del test
    if (file_exists(__DIR__ . '/../TestEnum.php.test')) {
        unlink(__DIR__ . '/../TestEnum.php.test');
    }
    if (file_exists(__DIR__ . '/../TestEnum.php.test.bak')) {
        unlink(__DIR__ . '/../TestEnum.php.test.bak');
    }
});

test('Añade múltiples casos con la opción --cases en formato PHP', function() {
    $application = new Application();
    $application->add(new BatchModifyEnumCommand(
        new CodeParser(),
        new FileHandler(),
        new EnumModifier()
    ));
    
    $command = $application->find('enum:batch-modify');
    $commandTester = new CommandTester($command);
    
    $commandTester->execute([
        'file' => __DIR__ . '/../TestEnum.php.test',
        '--cases' => "case BATCH_ONE = 'batch_value1'; case BATCH_TWO = 'batch_value2';"
    ]);
    
    $commandTester->assertCommandIsSuccessful();
    $output = $commandTester->getDisplay();
    
    // Verificar que el mensaje de éxito se muestra
    expect($output)->toContain('Case BATCH_ONE added');
    expect($output)->toContain('Case BATCH_TWO added');
    expect($output)->toContain('Total cases added: 2');
    
    // Verificar que el archivo fue modificado correctamente
    $fileContent = file_get_contents(__DIR__ . '/../TestEnum.php.test');
    expect($fileContent)->toContain('case BATCH_ONE = \'batch_value1\';');
    expect($fileContent)->toContain('case BATCH_TWO = \'batch_value2\';');
});

test('Añade múltiples casos con la opción --cases-raw en formato multilinea', function() {
    $application = new Application();
    $application->add(new BatchModifyEnumCommand(
        new CodeParser(),
        new FileHandler(),
        new EnumModifier()
    ));
    
    $command = $application->find('enum:batch-modify');
    $commandTester = new CommandTester($command);
    
    $multilineInput = <<<EOT
    case RAW_ONE = 'raw_value1';
    case RAW_TWO = 'raw_value2';
    case RAW_THREE = 'raw_value3';
    EOT;
    
    $commandTester->execute([
        'file' => __DIR__ . '/../TestEnum.php.test',
        '--cases-raw' => $multilineInput
    ]);
    
    $commandTester->assertCommandIsSuccessful();
    $output = $commandTester->getDisplay();
    
    // Verificar que el mensaje de éxito se muestra
    expect($output)->toContain('Case RAW_ONE added');
    expect($output)->toContain('Case RAW_TWO added');
    expect($output)->toContain('Case RAW_THREE added');
    expect($output)->toContain('Total cases added: 3');
    
    // Verificar que el archivo fue modificado correctamente
    $fileContent = file_get_contents(__DIR__ . '/../TestEnum.php.test');
    expect($fileContent)->toContain('case RAW_ONE = \'raw_value1\';');
    expect($fileContent)->toContain('case RAW_TWO = \'raw_value2\';');
    expect($fileContent)->toContain('case RAW_THREE = \'raw_value3\';');
});

test('Acepta formato simplificado sin la palabra case', function() {
    $application = new Application();
    $application->add(new BatchModifyEnumCommand(
        new CodeParser(),
        new FileHandler(),
        new EnumModifier()
    ));
    
    $command = $application->find('enum:batch-modify');
    $commandTester = new CommandTester($command);
    
    $multilineInput = <<<EOT
    SIMPLIFIED_ONE = 'simple_value1';
    SIMPLIFIED_TWO = 'simple_value2';
    EOT;
    
    $commandTester->execute([
        'file' => __DIR__ . '/../TestEnum.php.test',
        '--cases-raw' => $multilineInput
    ]);
    
    $commandTester->assertCommandIsSuccessful();
    $output = $commandTester->getDisplay();
    
    // Verificar que el mensaje de éxito se muestra
    expect($output)->toContain('Case SIMPLIFIED_ONE added');
    expect($output)->toContain('Case SIMPLIFIED_TWO added');
    expect($output)->toContain('Total cases added: 2');
    
    // Verificar que el archivo fue modificado correctamente
    $fileContent = file_get_contents(__DIR__ . '/../TestEnum.php.test');
    expect($fileContent)->toContain('case SIMPLIFIED_ONE = \'simple_value1\';');
    expect($fileContent)->toContain('case SIMPLIFIED_TWO = \'simple_value2\';');
});

test('Falla si no se proporciona ninguna opción de casos', function() {
    $application = new Application();
    $application->add(new BatchModifyEnumCommand(
        new CodeParser(),
        new FileHandler(),
        new EnumModifier()
    ));
    
    $command = $application->find('enum:batch-modify');
    $commandTester = new CommandTester($command);
    
    $commandTester->execute([
        'file' => __DIR__ . '/../TestEnum.php.test'
    ]);
    
    // El comando debe fallar
    expect($commandTester->getStatusCode())->toBe(1);
    
    $output = $commandTester->getDisplay();
    expect($output)->toContain('You must provide either --cases or --cases-raw option');
}); 