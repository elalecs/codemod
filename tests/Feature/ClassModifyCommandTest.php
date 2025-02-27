<?php

use Symfony\Component\Console\Tester\CommandTester;
use CodeModTool\Commands\ClassModifyCommand;
use CodeModTool\Parser\CodeParser;
use CodeModTool\FileHandler;
use CodeModTool\Modifiers\ClassModifier;
use Symfony\Component\Console\Application;

beforeEach(function () {
    // Create a test class in a temporary directory
    $this->testClassPath = createTestClass();
    
    // Create files to test trait imports in a temporary directory
    $this->traitsFilePath = tempnam(sys_get_temp_dir(), 'traits_file_') . '.txt';
    file_put_contents($this->traitsFilePath, "TestTrait1\nTestTrait2\nApp\\Traits\\TestTrait3");
    
    // Create a file for properties in a temporary directory
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
    
    // Create a file for methods in a temporary directory
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
    
    // Copy method files from Fixtures if needed
    $fixturesDir = __DIR__ . '/../Fixtures/';
    $this->methodStubPath = tempnam(sys_get_temp_dir(), 'method_stub_') . '.php';
    file_put_contents($this->methodStubPath, file_exists($fixturesDir . 'method_stub.php') 
        ? file_get_contents($fixturesDir . 'method_stub.php')
        : 'public function testMethod(): string { return "test"; }');
});

afterEach(function () {
    // Clean up after the test
    cleanupTestFiles([
        $this->testClassPath,
        $this->traitsFilePath,
        $this->propertiesFilePath,
        $this->methodsFilePath,
        $this->methodStubPath
    ]);
});

// Test for adding a trait
test('Case 01: class:modify adds a trait to a class', function () {
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
    
    // Verify success message
    expect($output)->toContain('Trait TestTrait added');
    
    // Verify that the trait was added to the file
    $classContent = file_get_contents($this->testClassPath);
    expect($classContent)->toContain('use TestTrait;');
});

// Test for adding multiple traits
test('Case 02: class:modify adds multiple traits with the --traits option', function () {
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
    
    // Verify success messages
    expect($output)->toContain('Trait Trait1 added');
    expect($output)->toContain('Trait Trait2 added');
    expect($output)->toContain('Trait App\\Traits\\Trait3 added');
    expect($output)->toContain('Total of traits added: 3');
    
    // Verify that the traits were added to the file
    $classContent = file_get_contents($this->testClassPath);
    expect($classContent)->toContain('use Trait1;');
    expect($classContent)->toContain('use Trait2;');
    expect($classContent)->toContain('use App\\Traits\\Trait3;');
});

// Test for adding traits from a file
test('Case 03: class:modify adds traits from a file', function () {
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
    
    // Verify success messages
    expect($output)->toContain('Trait TestTrait1 added');
    expect($output)->toContain('Trait TestTrait2 added');
    expect($output)->toContain('Trait App\\Traits\\TestTrait3 added');
    expect($output)->toContain('Total of traits added: 3');
    
    // Verify that the traits were added to the file
    $classContent = file_get_contents($this->testClassPath);
    expect($classContent)->toContain('use TestTrait1;');
    expect($classContent)->toContain('use TestTrait2;');
    expect($classContent)->toContain('use App\\Traits\\TestTrait3;');
});

// Test for adding a property
test('Case 04: class:modify adds a property to a class', function () {
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
    
    // Verify success message
    expect($output)->toContain('Property testProp added');
    
    // Verify that the property was added to the file
    $classContent = file_get_contents($this->testClassPath);
    expect($classContent)->toContain('private string $testProp = \'Test Value\';');
});

// Test for adding multiple properties from JSON
test('Case 05: class:modify adds multiple properties with the --properties option', function () {
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
    
    // Verify success messages
    expect($output)->toContain('Property prop1 added');
    expect($output)->toContain('Property prop2 added');
    expect($output)->toContain('Total of properties added: 2');
    
    // Verify that the properties were added to the file
    $classContent = file_get_contents($this->testClassPath);
    expect($classContent)->toContain('private string $prop1 = \'value1\';');
    expect($classContent)->toContain('protected int $prop2 = 42;');
});

// Test for adding properties from a file
test('Case 06: class:modify adds properties from a file', function () {
    // Create properties file
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
    
    // Verify success messages
    expect($output)->toContain('Property propFromFile1 added from file');
    expect($output)->toContain('Property propFromFile2 added from file');
    expect($output)->toContain('Total of properties added: 2');
    
    // Verify that the properties were added to the file using the helper function
    expect(class_has_property($this->testClassPath, 'propFromFile1', 'string', 'private'))->toBeTrue();
    expect(class_has_property($this->testClassPath, 'propFromFile2', 'array', 'protected'))->toBeTrue();
});

// Test for modifying an existing property
test('Case 07: class:modify modifies an existing property', function () {
    $application = new Application();
    $application->add(new ClassModifyCommand(
        new CodeParser(),
        new FileHandler(),
        new ClassModifier()
    ));
    
    $command = $application->find('class:modify');
    
    // First, add a property
    $commandTester = new CommandTester($command);
    $commandTester->execute([
        'file' => $this->testClassPath,
        '--property' => 'propToModify:string=original value'
    ]);
    
    // Verify that the property was added correctly
    expect(class_has_property($this->testClassPath, 'propToModify', 'string', 'private'))->toBeTrue();
    
    // Now modify it
    $commandTester = new CommandTester($command);
    $commandTester->execute([
        'file' => $this->testClassPath,
        '--modify-property' => 'propToModify',
        '--new-value' => 'modified value',
        '--new-visibility' => 'protected'
    ]);
    
    $commandTester->assertCommandIsSuccessful();
    $output = $commandTester->getDisplay();
    
    // Verify success message
    expect($output)->toContain('Property propToModify modified');
    
    // Verify that the property was modified correctly
    expect(class_has_property($this->testClassPath, 'propToModify', 'string', 'protected'))->toBeTrue();
});

// Test for adding an element to an array
test('Case 08: class:modify adds an element to a property array', function () {
    $application = new Application();
    $application->add(new ClassModifyCommand(
        new CodeParser(),
        new FileHandler(),
        new ClassModifier()
    ));
    
    $command = $application->find('class:modify');
    
    // First, add a property array
    $commandTester = new CommandTester($command);
    $commandTester->execute([
        'file' => $this->testClassPath,
        '--property' => 'items:array=["item1", "item2"]'
    ]);
    
    // Now add an element to the array
    $commandTester = new CommandTester($command);
    $commandTester->execute([
        'file' => $this->testClassPath,
        '--add-to-array' => 'items',
        '--array-value' => 'item3'
    ]);
    
    $commandTester->assertCommandIsSuccessful();
    $output = $commandTester->getDisplay();
    
    // Verify success message
    expect($output)->toContain('Element added to array items');
    
    // Verify that the element was added to the array in the file
    $classContent = file_get_contents($this->testClassPath);
    expect($classContent)->toContain('item3');
});

// Test for adding a method
test('Case 09: class:modify adds a method to a class', function () {
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
    
    // Verify success message
    expect($output)->toContain('Method testMethod added');
    
    // Verify that the method was added correctly
    expect(class_has_method($this->testClassPath, 'testMethod', 'string', 'public'))->toBeTrue();
});

// Test for adding multiple methods
test('Case 10: class:modify adds multiple methods with the --methods option', function () {
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
    
    // Verify success messages
    expect($output)->toContain('Method method1 added');
    expect($output)->toContain('Method method2 added');
    expect($output)->toContain('Total of methods added: 2');
    
    // Verify that the methods were added correctly
    expect(class_has_method($this->testClassPath, 'method1', 'string', 'public'))->toBeTrue();
    expect(class_has_method($this->testClassPath, 'method2', null, 'protected'))->toBeTrue();
});

// Test for adding methods from a file
test('Case 11: class:modify adds methods from a file', function () {
    // Create methods file
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
    
    // Verify success messages
    expect($output)->toContain('Method fileMethod1 added');
    expect($output)->toContain('Method fileMethod2 added');
    
    // Verify that the methods were added correctly
    expect(class_has_method($this->testClassPath, 'fileMethod1', 'array', 'public'))->toBeTrue();
    expect(class_has_method($this->testClassPath, 'fileMethod2', 'bool', 'protected'))->toBeTrue();
});

// Test for combining different types of modifications
test('Case 12: class:modify allows combining different types of modifications', function () {
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
    
    // Verify success messages
    expect($output)->toContain('Trait CombinedTrait added');
    expect($output)->toContain('Property combinedProp added');
    expect($output)->toContain('Method combinedMethod added');
    
    // Verify that all modifications were applied
    expect(class_uses_trait($this->testClassPath, 'CombinedTrait'))->toBeTrue();
    expect(class_has_property($this->testClassPath, 'combinedProp', 'string', 'private'))->toBeTrue();
    expect(class_has_method($this->testClassPath, 'combinedMethod', 'void', 'public'))->toBeTrue();
});

// Test for dry-run mode
test('Case 13: class:modify in dry-run mode shows changes without applying them', function () {
    $application = new Application();
    $application->add(new ClassModifyCommand(
        new CodeParser(),
        new FileHandler(),
        new ClassModifier()
    ));
    
    $command = $application->find('class:modify');
    $commandTester = new CommandTester($command);
    
    // Save original code for comparison
    $originalCode = file_get_contents($this->testClassPath);
    
    $commandTester->execute([
        'file' => $this->testClassPath,
        '--trait' => 'DryRunTrait',
        '--property' => 'dryRunProp:string=Dry run value',
        '--method' => 'public function dryRunMethod(): void { /* dry run method */ }',
        '--dry-run' => true
    ]);
    
    $commandTester->assertCommandIsSuccessful();
    $output = $commandTester->getDisplay();
    
    // Verify success messages
    expect($output)->toContain('Dry-run mode: Showing changes without applying them');
    expect($output)->toContain('Trait DryRunTrait added');
    expect($output)->toContain('Property dryRunProp added');
    expect($output)->toContain('Method dryRunMethod added');
    
    // Verify that the code was not modified
    $codeAfter = file_get_contents($this->testClassPath);
    expect($codeAfter)->toBe($originalCode);
});

// Test for verifying that dry-run mode does not modify the file
test('Case 14: class:modify in dry-run mode does not modify the file', function () {
    $application = new Application();
    $application->add(new ClassModifyCommand(
        new CodeParser(),
        new FileHandler(),
        new ClassModifier()
    ));
    
    $command = $application->find('class:modify');
    
    // Save original file content
    $originalCode = file_get_contents($this->testClassPath);
    
    $commandTester = new CommandTester($command);
    $commandTester->execute([
        'file' => $this->testClassPath,
        '--method' => 'public function dryRunMethod() { return "dry run"; }',
        '--dry-run' => true
    ]);
    
    $commandTester->assertCommandIsSuccessful();
    $output = $commandTester->getDisplay();
    
    // Verify that dry-run message is shown
    expect($output)->toContain('Dry-run mode');
    expect($output)->toContain('Method dryRunMethod added');
    
    // Verify that the code was not modified
    $codeAfter = file_get_contents($this->testClassPath);
    expect($codeAfter)->toBe($originalCode);
}); 