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
    
    public function addCase(Enum_ $enum, string $name, string $value): void
    {
        $enum->stmts[] = new EnumCase(
            $name,
            new String_($value)
        );
    }
    
    /**
     * Adds a method to an enum from a stub or code
     */
    public function addMethod(Enum_ $enum, string $methodStub): bool
    {
        try {
            // Check if the stub starts with <?php and add it if not present
            if (!str_starts_with(trim($methodStub), '<?php')) {
                // Create a temporary enum with the method
                $tempCode = "<?php\nenum TempEnum {\ncase TEST = 'test';\n    $methodStub\n}";
            } else {
                // If it already has the PHP tag, remove it and create the temporary enum
                $methodStub = preg_replace('/^\s*<\?php\s*/i', '', $methodStub);
                $tempCode = "<?php\nenum TempEnum {\ncase TEST = 'test';\n    $methodStub\n}";
            }
            
            $ast = $this->parser->parse($tempCode);
            
            if (!$ast) {
                return false;
            }
            
            // Search for the method in the temporary enum
            $methodNode = null;
            
            // Traverse the AST to find the enum and the method
            foreach ($ast as $node) {
                if ($node instanceof Enum_) {
                    foreach ($node->stmts as $stmt) {
                        if ($stmt instanceof ClassMethod) {
                            $methodNode = $stmt;
                            break 2;
                        }
                    }
                }
            }
            
            if (!$methodNode) {
                return false;
            }
            
            // Check if the method already exists in the target enum
            $methodName = $methodNode->name->toString();
            foreach ($enum->stmts as $stmt) {
                if ($stmt instanceof ClassMethod && $stmt->name->toString() === $methodName) {
                    return false; // The method already exists
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
