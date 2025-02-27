<?php

namespace CodeModTool\Modifiers;

use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\EnumCase;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Parser;
use PhpParser\ParserFactory;

class EnumModifier
{
    private Parser $parser;
    
    public function __construct()
    {
        $this->parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
    }
    
    public function addCase(Enum_ $enum, string $name, ?string $value = null): void
    {
        $isPureEnum = $enum->scalarType === null;
        
        // Initialize stmts array if it doesn't exist
        if (!isset($enum->stmts)) {
            $enum->stmts = [];
        }
        
        if ($isPureEnum) {
            $enum->stmts[] = new EnumCase($name);
        } else {
            $enum->stmts[] = new EnumCase(
                $name,
                new String_($value)
            );
        }
    }
    
    /**
     * Adds a method to an enum from a stub or code
     */
    public function addMethod(Enum_ $enum, string $methodStub): bool
    {
        try {
            // Initialize stmts array if it doesn't exist
            if (!isset($enum->stmts)) {
                $enum->stmts = [];
            }
            
            // Check if the enum is pure or backed
            $isPureEnum = $enum->scalarType === null;
            
            // Remove PHP tags and any namespace declarations
            $methodStub = preg_replace('/^\s*<\?php\s*/i', '', $methodStub);
            $methodStub = preg_replace('/^\s*namespace\s+[^;]+;\s*/m', '', $methodStub);
            
            // Create a temporary enum with the method
            $tempCode = "<?php\n";
            if ($isPureEnum) {
                $tempCode .= "enum TempEnum {\n    case TEST;\n    " . trim($methodStub) . "\n}";
            } else {
                $tempCode .= "enum TempEnum: string {\n    case TEST = 'test';\n    " . trim($methodStub) . "\n}";
            }
            
            // Parse the temporary code
            $ast = $this->parser->parse($tempCode);
            if (!$ast) {
                throw new \RuntimeException('Failed to parse method code');
            }
            
            // Find the method node
            $methodNode = null;
            foreach ($ast as $node) {
                if ($node instanceof Enum_) {
                    foreach ($node->stmts as $stmt) {
                        if ($stmt instanceof ClassMethod) {
                            $methodNode = clone $stmt;
                            break 2;
                        }
                    }
                }
            }
            
            if (!$methodNode) {
                throw new \RuntimeException('No method found in the provided code');
            }
            
            // Check if the method already exists
            $methodName = $methodNode->name->toString();
            foreach ($enum->stmts as $stmt) {
                if ($stmt instanceof ClassMethod && $stmt->name->toString() === $methodName) {
                    return false;
                }
            }
            
            // Add the method to the enum
            $enum->stmts[] = $methodNode;
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
