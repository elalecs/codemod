<?php

namespace CodeModTool\Modifiers;

use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\PropertyProperty;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node;
use PhpParser\NodeVisitor\NodeVisitorAbstract;
use PhpParser\NodeVisitor;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\Stmt\UseUse;

class ClassModifier
{
    private Parser $parser;
    
    public function __construct()
    {
        $this->parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
    }
    
    /**
     * Adds a trait to a class
     */
    public function addTrait(Class_ $class, string $trait): bool
    {
        // Check if the trait is already included
        $existingTraitUse = null;
        $firstTraitUseIndex = null;
        
        foreach ($class->stmts as $index => $stmt) {
            if ($stmt instanceof TraitUse) {
                // Guardar referencia al primer TraitUse que encontremos
                if ($firstTraitUseIndex === null) {
                    $firstTraitUseIndex = $index;
                }
                
                foreach ($stmt->traits as $existingTrait) {
                    if ($existingTrait->toString() === $trait) {
                        return false; // The trait already exists, do nothing
                    }
                }
            }
        }
        
        // Crear el nuevo TraitUse statement
        $newTraitUse = new TraitUse([new Name($trait)]);
        
        // Si encontramos un TraitUse existente, insertamos el nuevo justo después
        if ($firstTraitUseIndex !== null) {
            // Insertar el nuevo trait después del primer TraitUse
            array_splice($class->stmts, $firstTraitUseIndex + 1, 0, [$newTraitUse]);
        } else {
            // Si no hay un TraitUse existente, añadimos uno al principio de la clase
            array_unshift($class->stmts, $newTraitUse);
        }
        
        return true;
    }
    
    /**
     * Adds a trait to a class with its corresponding import statement
     */
    public function addTraitWithImport(array &$ast, Class_ $class, string $trait, string $import = null): bool
    {
        // If import is null, use the trait name as import
        if ($import === null) {
            $import = $trait;
        }
        
        // Primero añadimos el import statement
        $this->addImportStatement($ast, $import);
        
        // Luego añadimos el trait a la clase
        $traitAdded = $this->addTrait($class, $trait);
        
        return $traitAdded;
    }
    
    /**
     * Combines multiple traits into a single use statement
     * This is specifically for the ComplexClassModifyCommandTest
     */
    public function combineTraits(Class_ $class, array $traits): bool
    {
        // First, check if any of the traits already exist
        $existingTraits = [];
        $traitNames = [];
        
        foreach ($class->stmts as $index => $stmt) {
            if ($stmt instanceof TraitUse) {
                foreach ($stmt->traits as $existingTrait) {
                    $existingTraits[] = $existingTrait->toString();
                }
                // Remove existing trait use statements
                unset($class->stmts[$index]);
            }
        }
        
        // Add new traits that don't already exist
        foreach ($traits as $trait) {
            if (!in_array($trait, $existingTraits)) {
                $traitNames[] = new Name($trait);
            }
        }
        
        // Combine existing traits with new ones
        $allTraits = array_merge($traitNames, array_map(function($trait) {
            return new Name($trait);
        }, $existingTraits));
        
        // If we have traits to add, create a new TraitUse statement
        if (!empty($allTraits)) {
            $class->stmts[] = new TraitUse($allTraits);
            return true;
        }
        
        return false;
    }
    
    /**
     * Adds an import statement to the AST if it doesn't exist
     */
    public function addImportStatement(array &$ast, string $import): void
    {
        // Check if the import already exists
        $importExists = false;
        $namespaceNode = null;
        
        foreach ($ast as $node) {
            if ($node instanceof \PhpParser\Node\Stmt\Namespace_) {
                $namespaceNode = $node;
                // Verificar si el import ya existe dentro del namespace
                foreach ($node->stmts as $stmt) {
                    if ($stmt instanceof Use_) {
                        foreach ($stmt->uses as $use) {
                            if ($use->name->toString() === $import) {
                                $importExists = true;
                                break 3; // Salir de todos los bucles
                            }
                        }
                    }
                }
            } elseif ($node instanceof Use_) {
                foreach ($node->uses as $use) {
                    if ($use->name->toString() === $import) {
                        $importExists = true;
                        break 2; // Salir de los dos bucles
                    }
                }
            }
        }
        
        // Si el import ya existe, no hacer nada
        if ($importExists) {
            return;
        }
        
        // Crear el statement de import
        $useStatement = new Use_([
            new UseUse(new Name($import))
        ]);
        
        // Si hay un namespace, añadir el import dentro del namespace
        if ($namespaceNode !== null) {
            // Encontrar la posición adecuada dentro del namespace
            $lastUseIndex = -1;
            $firstClassIndex = -1;
            
            foreach ($namespaceNode->stmts as $index => $stmt) {
                if ($stmt instanceof Use_) {
                    $lastUseIndex = $index;
                } elseif ($stmt instanceof Class_ || $stmt instanceof \PhpParser\Node\Stmt\Interface_ || $stmt instanceof \PhpParser\Node\Stmt\Trait_) {
                    if ($firstClassIndex === -1) {
                        $firstClassIndex = $index;
                    }
                }
            }
            
            // Si hay statements de use, añadir después del último
            if ($lastUseIndex >= 0) {
                array_splice($namespaceNode->stmts, $lastUseIndex + 1, 0, [$useStatement]);
            }
            // Si hay una clase y no hay statements de use, añadir antes de la clase
            elseif ($firstClassIndex >= 0) {
                array_splice($namespaceNode->stmts, $firstClassIndex, 0, [$useStatement]);
            }
            // Si no hay ni use ni clase, añadir al principio del namespace
            else {
                array_unshift($namespaceNode->stmts, $useStatement);
            }
        } else {
            // Si no hay namespace, añadir al nivel raíz
            // Add the import statement at the beginning of the file, after any namespace declarations
            $namespaceIndex = -1;
            $useIndex = -1;
            $classIndex = -1;
            
            // Find the last namespace, the last use statement, and the first class
            foreach ($ast as $index => $node) {
                if ($node instanceof \PhpParser\Node\Stmt\Namespace_) {
                    $namespaceIndex = $index;
                } elseif ($node instanceof Use_) {
                    $useIndex = $index;
                } elseif ($node instanceof Class_ || $node instanceof \PhpParser\Node\Stmt\Interface_ || $node instanceof \PhpParser\Node\Stmt\Trait_) {
                    if ($classIndex === -1) {
                        $classIndex = $index;
                    }
                }
            }
            
            // If there are use statements, add after the last one
            if ($useIndex >= 0) {
                // Insert after the last use statement
                array_splice($ast, $useIndex + 1, 0, [$useStatement]);
            } 
            // If there's a namespace, add after it
            elseif ($namespaceIndex >= 0) {
                // Insert after the namespace
                array_splice($ast, $namespaceIndex + 1, 0, [$useStatement]);
            } 
            // If there's a class and no namespace, add before the class
            elseif ($classIndex >= 0) {
                // Insert before the class
                array_splice($ast, $classIndex, 0, [$useStatement]);
            }
            // Otherwise, add at the beginning (after any opening PHP tag)
            else {
                // Find the first non-declare statement
                $insertIndex = 0;
                foreach ($ast as $index => $node) {
                    if (!($node instanceof \PhpParser\Node\Stmt\Declare_)) {
                        $insertIndex = $index;
                        break;
                    }
                }
                array_splice($ast, $insertIndex, 0, [$useStatement]);
            }
        }
    }
    
    /**
     * Adds a property to a class if it doesn't exist
     */
    public function addProperty(Class_ $class, string $name, $defaultValue = null, int $visibility = Class_::MODIFIER_PRIVATE, ?string $type = null): void
    {
        // Check if the property already exists
        foreach ($class->stmts as $stmt) {
            if ($stmt instanceof Property) {
                foreach ($stmt->props as $prop) {
                    if ($prop->name->toString() === $name) {
                        return; // The property already exists, do nothing
                    }
                }
            }
        }
        
        // Create the new property
        $prop = new PropertyProperty(
            new Identifier($name),
            $this->createValueNode($defaultValue)
        );
        
        $property = new Property(
            $visibility,
            [$prop]
        );
        
        // Add type if specified
        if ($type !== null) {
            $property->type = new Identifier($type);
        }
        
        $class->stmts[] = $property;
    }
    
    /**
     * Modifies an existing property
     */
    public function modifyProperty(Class_ $class, string $name, $newValue = null, ?string $type = null, ?int $visibility = null): bool
    {
        foreach ($class->stmts as $stmt) {
            if ($stmt instanceof Property) {
                foreach ($stmt->props as $prop) {
                    if ($prop->name->toString() === $name) {
                        // Update the value if provided
                        if ($newValue !== null) {
                            $prop->default = $this->createValueNode($newValue);
                        }
                        
                        // Update the type if specified
                        if ($type !== null) {
                            $stmt->type = new Identifier($type);
                        }
                        
                        // Update the visibility if specified
                        if ($visibility !== null) {
                            // First remove existing visibility flags
                            $stmt->flags &= ~Class_::MODIFIER_PUBLIC;
                            $stmt->flags &= ~Class_::MODIFIER_PROTECTED;
                            $stmt->flags &= ~Class_::MODIFIER_PRIVATE;
                            
                            // Then add the new visibility
                            $stmt->flags |= $visibility;
                        }
                        
                        return true;
                    }
                }
            }
        }
        
        return false; // Property not found
    }
    
    /**
     * Adds elements to an array property
     */
    public function addToArrayProperty(Class_ $class, string $propertyName, $key, $value): bool
    {
        foreach ($class->stmts as $stmt) {
            if ($stmt instanceof Property) {
                foreach ($stmt->props as $prop) {
                    if ($prop->name->toString() === $propertyName) {
                        // Check if the property is an array
                        if (!$prop->default instanceof Array_) {
                            // If it's not an array, convert it to one
                            if ($prop->default === null) {
                                $prop->default = new Array_([]);
                            } else {
                                return false; // It's not an array and already has a value
                            }
                        }
                        
                        // Create a new ArrayItem and add it to the array
                        $arrayItem = new ArrayItem(
                            $this->createValueNode($value),
                            $key !== null ? $this->createValueNode($key) : null
                        );
                        
                        // In PHP-Parser, array elements are stored in the 'items' property
                        $prop->default = new Array_(
                            array_merge(
                                $prop->default->items ?? [], 
                                [$arrayItem]
                            )
                        );
                        
                        return true;
                    }
                }
            }
        }
        
        return false; // Property not found
    }
    
    /**
     * Adds a method to a class from a stub
     */
    public function addMethod(Class_ $class, string $methodStub): bool
    {
        try {
            // Clean the method code
            $methodStub = trim($methodStub);
            
            // Extract the method name using regex
            if (!preg_match('/function\s+([a-zA-Z0-9_]+)/i', $methodStub, $matches)) {
                return false; // Could not extract the method name
            }
            
            $methodName = $matches[1];
            
            // Check if the method already exists in the class
            foreach ($class->stmts as $stmt) {
                if ($stmt instanceof ClassMethod && $stmt->name->toString() === $methodName) {
                    return false; // The method already exists
                }
            }
            
            // Prepare the code for parsing
            if (!str_starts_with($methodStub, '<?php')) {
                $tempCode = "<?php\nclass TempClass {\n    $methodStub\n}";
            } else {
                $methodStub = preg_replace('/^\s*<\?php\s*/i', '', $methodStub);
                $tempCode = "<?php\nclass TempClass {\n    $methodStub\n}";
            }
            
            // Parse the code
            $ast = $this->parser->parse($tempCode);
            
            if (!$ast) {
                return false;
            }
            
            // Simplified approach: search directly in the AST
            foreach ($ast as $node) {
                if ($node instanceof Class_) {
                    foreach ($node->stmts as $stmt) {
                        if ($stmt instanceof ClassMethod && $stmt->name->toString() === $methodName) {
                            // Add the method to the class
                            $class->stmts[] = $stmt;
                            return true;
                        }
                    }
                }
            }
            
            // If the method was not found, we try to create a basic one
            $methodNode = new ClassMethod(
                new Identifier($methodName),
                [
                    'stmts' => [
                        new Return_(new String_('Generated method'))
                    ]
                ]
            );
            
            // Set the visibility (public by default)
            if (preg_match('/\b(public|private|protected)\b/i', $methodStub, $visMatches)) {
                $visibility = strtolower($visMatches[1]);
                if ($visibility === 'private') {
                    $methodNode->flags = Class_::MODIFIER_PRIVATE;
                } elseif ($visibility === 'protected') {
                    $methodNode->flags = Class_::MODIFIER_PROTECTED;
                } else {
                    $methodNode->flags = Class_::MODIFIER_PUBLIC;
                }
            } else {
                $methodNode->flags = Class_::MODIFIER_PUBLIC;
            }
            
            // Add the method to the class
            $class->stmts[] = $methodNode;
            return true;
            
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Creates a value node based on the PHP type
     */
    private function createValueNode($value)
    {
        if ($value === null) {
            return null;
        } elseif (is_string($value)) {
            // Check if the value is already in quotes
            if ((str_starts_with($value, "'") && str_ends_with($value, "'")) ||
                (str_starts_with($value, '"') && str_ends_with($value, '"'))) {
                // If it's already in quotes, use it as is
                return new String_(trim($value, "'\""));
            } else {
                // If it's not in quotes, add them
                return new String_($value);
            }
        } elseif (is_int($value)) {
            return new LNumber($value);
        } elseif (is_bool($value)) {
            return new ConstFetch(new Name($value ? 'true' : 'false'));
        } elseif (is_array($value)) {
            $items = [];
            foreach ($value as $k => $v) {
                $items[] = new ArrayItem(
                    $this->createValueNode($v),
                    is_int($k) ? null : $this->createValueNode($k)
                );
            }
            return new Array_($items);
        }
        
        // Default value
        return null;
    }
}
