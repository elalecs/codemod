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
     * Añade un método a un enum desde un stub o código
     */
    public function addMethod(Enum_ $enum, string $methodStub): bool
    {
        try {
            // Verificar si el stub comienza con <?php y añadirlo si no está presente
            if (!str_starts_with(trim($methodStub), '<?php')) {
                // Crear un enum temporal con el método
                $tempCode = "<?php\nenum TempEnum {\ncase TEST = 'test';\n    $methodStub\n}";
            } else {
                // Si ya tiene la etiqueta PHP, eliminarla y crear el enum temporal
                $methodStub = preg_replace('/^\s*<\?php\s*/i', '', $methodStub);
                $tempCode = "<?php\nenum TempEnum {\ncase TEST = 'test';\n    $methodStub\n}";
            }
            
            $ast = $this->parser->parse($tempCode);
            
            if (!$ast) {
                return false;
            }
            
            // Buscar el método en el enum temporal
            $methodNode = null;
            
            // Recorrer el AST para encontrar el enum y el método
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
            
            // Verificar si el método ya existe en el enum destino
            $methodName = $methodNode->name->toString();
            foreach ($enum->stmts as $stmt) {
                if ($stmt instanceof ClassMethod && $stmt->name->toString() === $methodName) {
                    return false; // El método ya existe
                }
            }
            
            // Añadir el método al enum
            $enum->stmts[] = $methodNode;
            return true;
            
        } catch (\Exception $e) {
            return false;
        }
    }
}
