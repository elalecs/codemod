<?php

use Symfony\Component\Console\Tester\CommandTester;
use CodeModTool\Commands\ClassModifyCommand;
use CodeModTool\Parser\CodeParser;
use CodeModTool\FileHandler;
use CodeModTool\Modifiers\ClassModifier;
use Symfony\Component\Console\Application;

beforeEach(function () {
    // Create a temporary test class file
    $this->testClassPath = createTestClassWithNamespace();
    
    // Create a test class with existing imports
    $this->testClassWithImportsPath = createTestClassWithExistingImports();
});

afterEach(function () {
    // Clean up test files
    cleanupTestFiles([
        $this->testClassPath,
        $this->testClassWithImportsPath
    ]);
});

/**
 * Case 01: Test that imports are added inside the namespace
 */
test('imports are added inside namespace', function () {
    $application = new Application();
    $application->add(new ClassModifyCommand(
        new CodeParser(),
        new FileHandler(),
        new ClassModifier()
    ));
    
    $command = $application->find('class:modify');
    $commandTester = new CommandTester($command);
    
    // Execute the command
    $commandTester->execute([
        'file' => $this->testClassPath,
        '--traits' => 'HasRoles',
        '--imports' => 'Spatie\\Permission\\Traits\\HasRoles'
    ]);
    
    $commandTester->assertCommandIsSuccessful();
    
    // Get the modified content
    $modifiedContent = file_get_contents($this->testClassPath);
    
    // Check that the import was added inside the namespace
    expect($modifiedContent)->toContain('namespace App\\Models;');
    expect($modifiedContent)->toContain('use Spatie\\Permission\\Traits\\HasRoles;');
    
    // Check that the import is after the namespace declaration and before the class declaration
    $namespacePos = strpos($modifiedContent, 'namespace App\\Models;');
    $importPos = strpos($modifiedContent, 'use Spatie\\Permission\\Traits\\HasRoles;');
    $classPos = strpos($modifiedContent, 'class User');
    
    expect($importPos)->toBeGreaterThan($namespacePos);
    expect($importPos)->toBeLessThan($classPos);
});

/**
 * Case 02: Test that multiple imports are added correctly
 */
test('multiple imports are added correctly', function () {
    $application = new Application();
    $application->add(new ClassModifyCommand(
        new CodeParser(),
        new FileHandler(),
        new ClassModifier()
    ));
    
    $command = $application->find('class:modify');
    $commandTester = new CommandTester($command);
    
    // Execute the command
    $commandTester->execute([
        'file' => $this->testClassPath,
        '--traits' => 'HasRoles,HasApiTokens,SoftDeletes',
        '--imports' => 'Spatie\\Permission\\Traits\\HasRoles,Laravel\\Sanctum\\HasApiTokens,Illuminate\\Database\\Eloquent\\SoftDeletes'
    ]);
    
    $commandTester->assertCommandIsSuccessful();
    
    // Get the modified content
    $modifiedContent = file_get_contents($this->testClassPath);
    
    // Check that all imports were added
    expect($modifiedContent)->toContain('use Spatie\\Permission\\Traits\\HasRoles;');
    expect($modifiedContent)->toContain('use Laravel\\Sanctum\\HasApiTokens;');
    expect($modifiedContent)->toContain('use Illuminate\\Database\\Eloquent\\SoftDeletes;');
    
    // Check that all traits were added
    expect($modifiedContent)->toContain('use HasRoles;');
    expect($modifiedContent)->toContain('use HasApiTokens;');
    expect($modifiedContent)->toContain('use SoftDeletes;');
});

/**
 * Case 03: Test that imports are not duplicated
 */
test('imports are not duplicated', function () {
    $application = new Application();
    $application->add(new ClassModifyCommand(
        new CodeParser(),
        new FileHandler(),
        new ClassModifier()
    ));
    
    $command = $application->find('class:modify');
    $commandTester = new CommandTester($command);
    
    // First add a trait with import
    $commandTester->execute([
        'file' => $this->testClassPath,
        '--traits' => 'HasRoles',
        '--imports' => 'Spatie\\Permission\\Traits\\HasRoles'
    ]);
    
    // Then try to add the same trait again
    $commandTester->execute([
        'file' => $this->testClassPath,
        '--traits' => 'HasRoles',
        '--imports' => 'Spatie\\Permission\\Traits\\HasRoles'
    ]);
    
    // Get the modified content
    $modifiedContent = file_get_contents($this->testClassPath);
    
    // Count occurrences of the import
    $importCount = substr_count($modifiedContent, 'use Spatie\\Permission\\Traits\\HasRoles;');
    
    // Check that the import appears only once
    expect($importCount)->toBe(1);
});

/**
 * Case 04: Test that imports are added in the correct order with existing imports
 */
test('imports are added in correct order with existing imports', function () {
    $application = new Application();
    $application->add(new ClassModifyCommand(
        new CodeParser(),
        new FileHandler(),
        new ClassModifier()
    ));
    
    $command = $application->find('class:modify');
    $commandTester = new CommandTester($command);
    
    // Execute the command
    $commandTester->execute([
        'file' => $this->testClassWithImportsPath,
        '--traits' => 'HasRoles',
        '--imports' => 'Spatie\\Permission\\Traits\\HasRoles'
    ]);
    
    $commandTester->assertCommandIsSuccessful();
    
    // Get the modified content
    $modifiedContent = file_get_contents($this->testClassWithImportsPath);
    
    // Check that the import was added after existing imports
    $existingImportPos = strpos($modifiedContent, 'use Illuminate\\Support\\Facades\\Log;');
    $newImportPos = strpos($modifiedContent, 'use Spatie\\Permission\\Traits\\HasRoles;');
    
    expect($newImportPos)->toBeGreaterThan($existingImportPos);
});
