<?php

use CodeModTool\Commands\ModifyEnumCommand;
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
    if (file_exists(__DIR__ . '/../cases_file.txt')) {
        unlink(__DIR__ . '/../cases_file.txt');
    }
});

test('Añade un caso simple a un enum', function() {
    $application = new Application();
    $application->add(new ModifyEnumCommand(
        new CodeParser(),
        new FileHandler(),
        new EnumModifier()
    ));
    
    $command = $application->find('enum:modify');
    $commandTester = new CommandTester($command);
    
    $commandTester->execute([
        'file' => __DIR__ . '/../TestEnum.php.test',
        '--case' => 'NEW_CASE',
        '--value' => 'new_value'
    ]);
    
    $commandTester->assertCommandIsSuccessful();
    $output = $commandTester->getDisplay();
    
    // Verificar que el mensaje de éxito se muestra
    expect($output)->toContain('Case NEW_CASE added');
    
    // Verificar que el archivo fue modificado correctamente
    $fileContent = file_get_contents(__DIR__ . '/../TestEnum.php.test');
    expect($fileContent)->toContain('case NEW_CASE = \'new_value\';');
});

test('Añade múltiples casos con la opción --cases', function() {
    $application = new Application();
    $application->add(new ModifyEnumCommand(
        new CodeParser(),
        new FileHandler(),
        new EnumModifier()
    ));
    
    $command = $application->find('enum:modify');
    $commandTester = new CommandTester($command);
    
    $commandTester->execute([
        'file' => __DIR__ . '/../TestEnum.php.test',
        '--cases' => 'MULTIPLE_ONE=value1,MULTIPLE_TWO=value2'
    ]);
    
    $commandTester->assertCommandIsSuccessful();
    $output = $commandTester->getDisplay();
    
    // Verificar que el mensaje de éxito se muestra
    expect($output)->toContain('Case MULTIPLE_ONE added');
    expect($output)->toContain('Case MULTIPLE_TWO added');
    expect($output)->toContain('Total cases added: 2');
    
    // Verificar que el archivo fue modificado correctamente
    $fileContent = file_get_contents(__DIR__ . '/../TestEnum.php.test');
    expect($fileContent)->toContain('case MULTIPLE_ONE = \'value1\';');
    expect($fileContent)->toContain('case MULTIPLE_TWO = \'value2\';');
});

test('Añade casos desde un archivo', function() {
    // Crear archivo de casos
    $casesContent = "CASE_FILE_ONE = 'file_value_1'\nCASE_FILE_TWO = 'file_value_2'";
    file_put_contents(__DIR__ . '/../cases_file.txt', $casesContent);
    
    $application = new Application();
    $application->add(new ModifyEnumCommand(
        new CodeParser(),
        new FileHandler(),
        new EnumModifier()
    ));
    
    $command = $application->find('enum:modify');
    $commandTester = new CommandTester($command);
    
    $commandTester->execute([
        'file' => __DIR__ . '/../TestEnum.php.test',
        '--cases-file' => __DIR__ . '/../cases_file.txt',
    ]);
    
    $commandTester->assertCommandIsSuccessful();
    $output = $commandTester->getDisplay();
    
    // Verificar que el mensaje de éxito se muestra
    expect($output)->toContain('Case CASE_FILE_ONE added');
    expect($output)->toContain('Case CASE_FILE_TWO added');
    expect($output)->toContain('Total cases added: 2');
    
    // Verificar que el archivo fue modificado correctamente
    $fileContent = file_get_contents(__DIR__ . '/../TestEnum.php.test');
    expect($fileContent)->toContain('case CASE_FILE_ONE = \'file_value_1\';');
    expect($fileContent)->toContain('case CASE_FILE_TWO = \'file_value_2\';');
});

test('Falla si no se proporciona ninguna opción de caso', function() {
    $application = new Application();
    $application->add(new ModifyEnumCommand(
        new CodeParser(),
        new FileHandler(),
        new EnumModifier()
    ));
    
    $command = $application->find('enum:modify');
    $commandTester = new CommandTester($command);
    
    $commandTester->execute([
        'file' => __DIR__ . '/../TestEnum.php.test'
    ]);
    
    // El comando debe fallar
    expect($commandTester->getStatusCode())->toBe(1);
    
    $output = $commandTester->getDisplay();
    expect($output)->toContain('You must provide either --case and --value, --cases, or --cases-file');
}); 