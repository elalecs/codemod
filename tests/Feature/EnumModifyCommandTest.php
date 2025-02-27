<?php

use CodeModTool\Commands\EnumModifyCommand;
use CodeModTool\FileHandler;
use CodeModTool\Modifiers\EnumModifier;
use CodeModTool\Parser\CodeParser;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

beforeEach(function() {
    // Create a working copy of the test enum in a temporary directory
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
    
    // Create a file to test the cases from file option
    $this->casesFilePath = tempnam(sys_get_temp_dir(), 'cases_file_') . '.txt';
    file_put_contents($this->casesFilePath, "CASE_FILE_A = 'value_a'\nCASE_FILE_B = 'value_b'");
    
    // Create stub files for methods
    $this->methodStub1Path = createTestStub('<?php
public function getName(): string
{
    return $this->name;
}');
    
    $this->methodStub2Path = createTestStub('public function getValue(): string
{
    return $this->value;
}');
    
    // Create a file to test the methods from file option
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
    // Clean up after the test
    cleanupTestFiles([
        $this->testEnumPath,
        $this->casesFilePath,
        $this->methodStub1Path,
        $this->methodStub2Path,
        $this->methodsFilePath
    ]);
});

/**
 * Checks if an enum has a specific case
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
 * Checks if a class has a specific method
 */
function classHasMethod(string $className, string $methodName): bool {
    if (!class_exists($className)) {
        return false;
    }
    
    return method_exists($className, $methodName);
}

/**
 * Loads the enum from a temporary file
 */
function loadTestEnum(string $filePath): string {
    // Include the generated file so PHP can find the class
    // We use a unique name to avoid collisions with other already loaded classes
    $tempClassName = 'TestEnum_' . uniqid();
    
    // Read the file content
    $code = file_get_contents($filePath);
    
    // Verify if the enum has namespace defined
    $namespacePattern = '/namespace\s+([^;]+);/';
    preg_match($namespacePattern, $code, $namespaceMatches);
    $namespace = $namespaceMatches[1] ?? null;
    
    // Modify the code to use the temporary class name
    $code = str_replace('TestEnum', $tempClassName, $code);
    
    // Ensure the code has the correct namespace
    if (!$namespace) {
        $code = preg_replace('/(<\?php)/', "$1\n\nnamespace Tests;", $code);
    }
    
    // Create a temporary file with the new class name
    $tempFile = sys_get_temp_dir() . '/' . $tempClassName . '.php';
    file_put_contents($tempFile, $code);
    
    // Load the file
    require_once $tempFile;
    
    // Return the full class name
    return $namespace ? "$namespace\\$tempClassName" : "Tests\\$tempClassName";
}

// Tests for individual cases
test('Adds a simple case to an enum', function() {
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
    
    // Verify that the success message is displayed
    expect($output)->toContain('Case NEW_CASE added');
    
    // Load the modified enum and verify that the case exists
    $enumClassName = loadTestEnum($this->testEnumPath);
    expect(enumHasCase($enumClassName, 'NEW_CASE'))->toBeTrue();
    
    // Verify that the case value is correct
    $caseValue = constant("$enumClassName::NEW_CASE")->value;
    expect($caseValue)->toBe('new_value');
});

// Test for adding multiple cases in a single command
test('Adds multiple cases to an enum with the --cases option', function() {
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
    
    // Verify success messages
    expect($output)->toContain('Case MULTI_A added');
    expect($output)->toContain('Case MULTI_B added');
    expect($output)->toContain('Total of cases added: 2');
    
    // Load the modified enum and verify that the cases exist
    $enumClassName = loadTestEnum($this->testEnumPath);
    expect(enumHasCase($enumClassName, 'MULTI_A'))->toBeTrue();
    expect(enumHasCase($enumClassName, 'MULTI_B'))->toBeTrue();
    
    // Verify that the case values are correct
    expect(constant("$enumClassName::MULTI_A")->value)->toBe('value_a');
    expect(constant("$enumClassName::MULTI_B")->value)->toBe('value_b');
});

// Test for adding cases from a file
test('Adds cases to an enum from a file', function() {
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
    
    // Verify success messages
    expect($output)->toContain('Case CASE_FILE_A added');
    expect($output)->toContain('Case CASE_FILE_B added');
    expect($output)->toContain('Total of cases added: 2');
    
    // Load the modified enum and verify that the cases exist
    $enumClassName = loadTestEnum($this->testEnumPath);
    expect(enumHasCase($enumClassName, 'CASE_FILE_A'))->toBeTrue();
    expect(enumHasCase($enumClassName, 'CASE_FILE_B'))->toBeTrue();
    
    // Verify that the case values are correct
    expect(constant("$enumClassName::CASE_FILE_A")->value)->toBe('value_a');
    expect(constant("$enumClassName::CASE_FILE_B")->value)->toBe('value_b');
});

// Tests for individual methods
test('Adds a method to an enum', function() {
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
    
    // Verify success message
    expect($output)->toContain('Method getDescription added');
    expect($output)->toContain('Total of methods added: 1');
    
    // Load the modified enum and verify that the method exists
    $enumClassName = loadTestEnum($this->testEnumPath);
    expect(classHasMethod($enumClassName, 'getDescription'))->toBeTrue();
    
    // Verify that the method works correctly
    $instance = constant("$enumClassName::CASE_ONE");
    expect($instance->getDescription())->toBe('Description for CASE_ONE');
});

// Test for adding multiple methods
test('Adds multiple methods to an enum with the --methods option', function() {
    $application = new Application();
    $application->add(new EnumModifyCommand(
        new CodeParser(),
        new FileHandler(),
        new EnumModifier()
    ));
    
    $command = $application->find('enum:modify');
    $commandTester = new CommandTester($command);
    
    $methodsContent = "
    public function method1(): string {
        return 'value1';
    }
    
    public function method2(int \$param): void {
        // Just to verify it exists
    }";
    
    $commandTester->execute([
        'file' => $this->testEnumPath,
        '--methods' => $methodsContent
    ]);
    
    $commandTester->assertCommandIsSuccessful();
    $output = $commandTester->getDisplay();
    
    // Verify success messages
    expect($output)->toContain('Method method1 added');
    expect($output)->toContain('Method method2 added');
    expect($output)->toContain('Total of methods added: 2');
    
    // Load the modified enum and verify that the methods exist
    $enumClassName = loadTestEnum($this->testEnumPath);
    expect(classHasMethod($enumClassName, 'method1'))->toBeTrue();
    expect(classHasMethod($enumClassName, 'method2'))->toBeTrue();
    
    // Verify that the first method works correctly
    $instance = constant("$enumClassName::CASE_ONE");
    expect($instance->method1())->toBe('value1');
});

// Test for adding methods from a file
test('Adds methods to an enum from a file', function() {
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
    
    // Verify success messages
    expect($output)->toContain('Method getLabel added');
    expect($output)->toContain('Method isDefault added');
    expect($output)->toContain('Total of methods added: 2');
    
    // Load the modified enum and verify that the methods exist
    $enumClassName = loadTestEnum($this->testEnumPath);
    expect(classHasMethod($enumClassName, 'getLabel'))->toBeTrue();
    expect(classHasMethod($enumClassName, 'isDefault'))->toBeTrue();
    
    // Verify that the methods work correctly
    $instance = constant("$enumClassName::CASE_ONE");
    expect($instance->getLabel())->toBe('case_one');
    expect($instance->isDefault())->toBeTrue();
    
    $instance2 = constant("$enumClassName::CASE_TWO");
    expect($instance2->isDefault())->toBeFalse();
});

// Test for combining case and method additions in a single operation
test('Adds cases and methods in a single operation', function() {
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
    
    // Verify success messages for cases
    expect($output)->toContain('Case COMBINED_A added');
    expect($output)->toContain('Case COMBINED_B added');
    expect($output)->toContain('Total of cases added: 2');
    
    // Verify success messages for methods
    expect($output)->toContain('Method getCombinedName added');
    expect($output)->toContain('Total of methods added: 1');
    
    // Load the modified enum and verify
    $enumClassName = loadTestEnum($this->testEnumPath);
    
    // Verify cases
    expect(enumHasCase($enumClassName, 'COMBINED_A'))->toBeTrue();
    expect(enumHasCase($enumClassName, 'COMBINED_B'))->toBeTrue();
    expect(constant("$enumClassName::COMBINED_A")->value)->toBe('value_a');
    expect(constant("$enumClassName::COMBINED_B")->value)->toBe('value_b');
    
    // Verify method
    expect(classHasMethod($enumClassName, 'getCombinedName'))->toBeTrue();
    $instance = constant("$enumClassName::COMBINED_A");
    expect($instance->getCombinedName())->toBe('COMBINED_COMBINED_A');
});

// Test for dry-run mode
test('Dry-run mode shows changes without applying them', function() {
    $application = new Application();
    $application->add(new EnumModifyCommand(
        new CodeParser(),
        new FileHandler(),
        new EnumModifier()
    ));
    
    $command = $application->find('enum:modify');
    $commandTester = new CommandTester($command);
    
    // Save original content for comparison
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
    
    // Verify success messages
    expect($output)->toContain('Dry-run mode: Showing changes without applying them');
    expect($output)->toContain('Case DRY_RUN_CASE added');
    expect($output)->toContain('Method dryRunMethod added');
    expect($output)->toContain('[DRY-RUN MODE] Changes NOT applied');
    
    // Verify that the code was not modified
    $codeAfter = file_get_contents($this->testEnumPath);
    expect($codeAfter)->toBe($originalCode);
    
    // Load the original enum and verify that the case and method don't exist
    $enumClassName = loadTestEnum($this->testEnumPath);
    expect(enumHasCase($enumClassName, 'DRY_RUN_CASE'))->toBeFalse();
    expect(classHasMethod($enumClassName, 'dryRunMethod'))->toBeFalse();
});

// Validation test: should fail if no option is provided
test('Fails if no option is provided', function() {
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
    
    expect($commandTester->getStatusCode())->toBe(1); // Should fail (code 1)
    $output = $commandTester->getDisplay();
    expect($output)->toContain('You must provide at least one option');
});

// Validation test: should fail if --case is provided without --value
test('Fails if --case is provided without --value', function() {
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
    
    expect($commandTester->getStatusCode())->toBe(1); // Should fail (code 1)
    $output = $commandTester->getDisplay();
    expect($output)->toContain('If --case is provided, --value must also be provided');
}); 