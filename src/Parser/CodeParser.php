<?php

namespace CodeModTool\Parser;

use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;

class CodeParser
{
    public function parse(string $code): array
    {
        $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
        return $parser->parse($code);
    }

    public function generateCode(array $ast): string
    {
        // Reordenar los imports y clases para asegurar que estén en el orden correcto
        $this->processAst($ast);
        
        $prettyPrinter = new Standard();
        return $prettyPrinter->prettyPrintFile($ast);
    }
    
    /**
     * Procesa el AST para reordenar los imports y clases
     */
    private function processAst(array &$ast): void
    {
        // Reordenar los imports que puedan haber quedado fuera de lugar
        foreach ($ast as $key => $node) {
            // Si encontramos un namespace, procesamos sus statements
            if ($node instanceof \PhpParser\Node\Stmt\Namespace_) {
                $this->processNamespaceNode($node);
            }
        }
        
        // Procesar los nodos a nivel raíz
        $this->processRootNodes($ast);
    }
    
    /**
     * Procesa los nodos dentro de un namespace
     */
    private function processNamespaceNode(\PhpParser\Node\Stmt\Namespace_ &$namespaceNode): void
    {
        $imports = [];
        $classes = [];
        $others = [];
        
        // Separar los statements del namespace en imports, clases y otros
        foreach ($namespaceNode->stmts as $stmt) {
            if ($stmt instanceof \PhpParser\Node\Stmt\Use_) {
                $imports[] = $stmt;
            } elseif ($stmt instanceof \PhpParser\Node\Stmt\Class_ || 
                      $stmt instanceof \PhpParser\Node\Stmt\Interface_ || 
                      $stmt instanceof \PhpParser\Node\Stmt\Trait_) {
                $classes[] = $stmt;
            } else {
                $others[] = $stmt;
            }
        }
        
        // Reconstruir los statements del namespace con el orden correcto
        $namespaceNode->stmts = array_merge($others, $imports, $classes);
    }
    
    /**
     * Procesa los nodos a nivel raíz
     */
    private function processRootNodes(array &$ast): void
    {
        $imports = [];
        $namespaces = [];
        $classes = [];
        $others = [];
        
        // Separar los nodos en imports, namespaces, clases y otros
        foreach ($ast as $node) {
            if ($node instanceof \PhpParser\Node\Stmt\Use_) {
                $imports[] = $node;
            } elseif ($node instanceof \PhpParser\Node\Stmt\Namespace_) {
                $namespaces[] = $node;
            } elseif ($node instanceof \PhpParser\Node\Stmt\Class_ || 
                      $node instanceof \PhpParser\Node\Stmt\Interface_ || 
                      $node instanceof \PhpParser\Node\Stmt\Trait_) {
                $classes[] = $node;
            } else {
                $others[] = $node;
            }
        }
        
        // Reconstruir el AST con el orden correcto
        $ast = array_merge($others, $namespaces, $imports, $classes);
    }
}
