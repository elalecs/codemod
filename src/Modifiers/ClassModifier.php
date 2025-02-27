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
        foreach ($class->stmts as $stmt) {
            if ($stmt instanceof TraitUse) {
                foreach ($stmt->traits as $existingTrait) {
                    if ($existingTrait->toString() === $trait) {
                        return false; // The trait already exists, do nothing
                    }
                }
            }
        }
        
        $class->stmts[] = new TraitUse([new Name($trait)]);
        return true;
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
