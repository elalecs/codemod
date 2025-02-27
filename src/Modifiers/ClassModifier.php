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

class ClassModifier
{
    private Parser $parser;
    
    public function __construct()
    {
        $this->parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
    }
    
    /**
     * Añade un trait a una clase
     */
    public function addTrait(Class_ $class, string $trait): bool
    {
        // Verificar si el trait ya está incluido
        foreach ($class->stmts as $stmt) {
            if ($stmt instanceof TraitUse) {
                foreach ($stmt->traits as $existingTrait) {
                    if ($existingTrait->toString() === $trait) {
                        return false; // El trait ya existe, no hacer nada
                    }
                }
            }
        }
        
        $class->stmts[] = new TraitUse([new Name($trait)]);
        return true;
    }
    
    /**
     * Añade una propiedad a una clase si no existe
     */
    public function addProperty(Class_ $class, string $name, $defaultValue = null, int $visibility = Class_::MODIFIER_PRIVATE, ?string $type = null): void
    {
        // Verificar si la propiedad ya existe
        foreach ($class->stmts as $stmt) {
            if ($stmt instanceof Property) {
                foreach ($stmt->props as $prop) {
                    if ($prop->name->toString() === $name) {
                        return; // La propiedad ya existe, no hacer nada
                    }
                }
            }
        }
        
        // Crear la nueva propiedad
        $prop = new PropertyProperty(
            new Identifier($name),
            $this->createValueNode($defaultValue)
        );
        
        $property = new Property(
            $visibility,
            [$prop]
        );
        
        // Añadir tipo si se especificó
        if ($type !== null) {
            $property->type = new Identifier($type);
        }
        
        $class->stmts[] = $property;
    }
    
    /**
     * Modifica una propiedad existente
     */
    public function modifyProperty(Class_ $class, string $name, $newValue, ?string $type = null): bool
    {
        foreach ($class->stmts as $stmt) {
            if ($stmt instanceof Property) {
                foreach ($stmt->props as $prop) {
                    if ($prop->name->toString() === $name) {
                        $prop->default = $this->createValueNode($newValue);
                        
                        // Actualizar el tipo si se especificó
                        if ($type !== null) {
                            $stmt->type = new Identifier($type);
                        }
                        
                        return true;
                    }
                }
            }
        }
        
        return false; // Propiedad no encontrada
    }
    
    /**
     * Añade elementos a una propiedad de tipo array
     */
    public function addToArrayProperty(Class_ $class, string $propertyName, $key, $value): bool
    {
        foreach ($class->stmts as $stmt) {
            if ($stmt instanceof Property) {
                foreach ($stmt->props as $prop) {
                    if ($prop->name->toString() === $propertyName) {
                        // Verificar si la propiedad es un array
                        if (!$prop->default instanceof Array_) {
                            // Si no es un array, convertirlo en uno
                            if ($prop->default === null) {
                                $prop->default = new Array_([]);
                            } else {
                                return false; // No es un array y ya tiene un valor
                            }
                        }
                        
                        // Crear un nuevo ArrayItem y añadirlo al array
                        $arrayItem = new ArrayItem(
                            $this->createValueNode($value),
                            $key !== null ? $this->createValueNode($key) : null
                        );
                        
                        // En PHP-Parser, los elementos del array se almacenan en la propiedad 'items'
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
        
        return false; // Propiedad no encontrada
    }
    
    /**
     * Añade un método a una clase desde un stub
     */
    public function addMethod(Class_ $class, string $methodStub): bool
    {
        try {
            // Verificar si el stub comienza con <?php y añadirlo si no está presente
            if (!str_starts_with(trim($methodStub), '<?php')) {
                // Crear una clase temporal con el método
                $tempCode = "<?php\nclass TempClass {\n    $methodStub\n}";
            } else {
                // Si ya tiene la etiqueta PHP, eliminarla y crear la clase temporal
                $methodStub = preg_replace('/^\s*<\?php\s*/i', '', $methodStub);
                $tempCode = "<?php\nclass TempClass {\n    $methodStub\n}";
            }
            
            $ast = $this->parser->parse($tempCode);
            
            if (!$ast) {
                return false;
            }
            
            // Buscar el método en la clase temporal
            $methodNode = null;
            
            // Recorrer el AST para encontrar la clase y el método
            foreach ($ast as $node) {
                if ($node instanceof Class_) {
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
            
            // Verificar si el método ya existe en la clase destino
            $methodName = $methodNode->name->toString();
            foreach ($class->stmts as $stmt) {
                if ($stmt instanceof ClassMethod && $stmt->name->toString() === $methodName) {
                    return false; // El método ya existe
                }
            }
            
            // Añadir el método a la clase
            $class->stmts[] = $methodNode;
            return true;
            
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Crea un nodo de valor basado en el tipo de PHP
     */
    private function createValueNode($value)
    {
        if ($value === null) {
            return null;
        } elseif (is_string($value)) {
            // Verificar si el valor ya está entre comillas
            if ((str_starts_with($value, "'") && str_ends_with($value, "'")) ||
                (str_starts_with($value, '"') && str_ends_with($value, '"'))) {
                // Si ya está entre comillas, usarlo como está
                return new String_(trim($value, "'\""));
            } else {
                // Si no está entre comillas, añadirlas
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
        
        // Valor por defecto
        return null;
    }
}
