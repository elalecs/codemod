<?php

namespace CodeModTool\Commands;

use CodeModTool\FileHandler;
use CodeModTool\Modifiers\ClassModifier;
use CodeModTool\Parser\CodeParser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use PhpParser\NodeTraverser;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeVisitor\CloningVisitor;

class BatchAddTraitsCommand extends Command
{
    public function __construct(
        private CodeParser $parser,
        private FileHandler $fileHandler,
        private ClassModifier $modifier
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('class:batch-add-traits')
            ->setDescription('Añadir múltiples traits a una clase')
            ->addArgument('file', InputArgument::REQUIRED, 'Ruta al archivo de clase')
            ->addOption('traits', 't', InputOption::VALUE_REQUIRED, 'Lista de traits separados por comas')
            ->addOption('traits-file', 'f', InputOption::VALUE_REQUIRED, 'Archivo con lista de traits (uno por línea)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Muestra los cambios sin aplicarlos');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filePath = $input->getArgument('file');
        $traitsList = $input->getOption('traits');
        $traitsFile = $input->getOption('traits-file');
        $isDryRun = $input->getOption('dry-run');
        
        if (!$traitsList && !$traitsFile) {
            throw new \InvalidArgumentException("Debe proporcionar --traits o --traits-file");
        }
        
        try {
            $code = $this->fileHandler->read($filePath);
            $ast = $this->parser->parse($code);
            
            $traverser = new NodeTraverser();
            $traverser->addVisitor(new CloningVisitor());
            $modifiedAst = $traverser->traverse($ast);
            
            // Encontrar la clase
            $classNode = null;
            foreach ($modifiedAst as $node) {
                if ($node instanceof Class_) {
                    $classNode = $node;
                    break;
                }
            }
            
            if (!$classNode) {
                $output->writeln("<error>No se encontró ninguna clase en el archivo especificado</error>");
                return Command::FAILURE;
            }
            
            // Procesar traits
            $traits = [];
            
            if ($traitsList) {
                $traits = array_merge($traits, $this->parseTraitsList($traitsList));
            }
            
            if ($traitsFile) {
                if (!file_exists($traitsFile)) {
                    $output->writeln("<error>Archivo de traits no encontrado: $traitsFile</error>");
                    return Command::FAILURE;
                }
                
                $fileContent = file_get_contents($traitsFile);
                $traits = array_merge($traits, $this->parseTraitsFromFile($fileContent));
            }
            
            if (empty($traits)) {
                $output->writeln("<error>No se encontraron traits válidos para añadir</error>");
                return Command::FAILURE;
            }
            
            // Añadir los traits a la clase
            $traitsAdded = 0;
            foreach ($traits as $trait) {
                if ($this->modifier->addTrait($classNode, $trait)) {
                    $traitsAdded++;
                    $output->writeln("<info>Trait $trait añadido</info>");
                } else {
                    $output->writeln("<comment>El trait $trait ya existe en la clase o no es válido</comment>");
                }
            }
            
            if ($traitsAdded === 0) {
                $output->writeln("<comment>No se añadieron traits (posiblemente ya existen en la clase)</comment>");
                return Command::SUCCESS;
            }
            
            $newCode = $this->parser->generateCode($modifiedAst);
            
            if ($isDryRun) {
                $output->writeln("<info>Cambios que se realizarían (modo dry-run):</info>");
                $diff = $this->generateSimpleDiff($code, $newCode);
                foreach ($diff as $line) {
                    $output->writeln($line);
                }
                return Command::SUCCESS;
            }
            
            $this->fileHandler->write($filePath, $newCode);
            $output->writeln("<info>Total de traits añadidos: $traitsAdded</info>");
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $output->writeln("<error>Error: {$e->getMessage()}</error>");
            return Command::FAILURE;
        }
    }
    
    private function parseTraitsList(string $traitsList): array
    {
        $traits = [];
        $items = explode(',', $traitsList);
        
        foreach ($items as $item) {
            $trait = trim($item);
            if (!empty($trait)) {
                $traits[] = $trait;
            }
        }
        
        return $traits;
    }
    
    private function parseTraitsFromFile(string $content): array
    {
        $traits = [];
        $lines = explode("\n", $content);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Ignorar líneas vacías y comentarios
            if (empty($line) || $line[0] === '#' || strpos($line, '//') === 0) {
                continue;
            }
            
            // Extraer el nombre del trait (soporta formatos diversos)
            if (preg_match('/^(?:use\s+)?([A-Za-z0-9_\\\\]+)(?:\s*;)?$/', $line, $matches)) {
                $traits[] = $matches[1];
            }
        }
        
        return $traits;
    }
    
    /**
     * Genera un diff simple entre dos cadenas de texto
     */
    private function generateSimpleDiff(string $original, string $new): array
    {
        $originalLines = explode("\n", $original);
        $newLines = explode("\n", $new);
        
        $diff = [];
        $diff[] = '<comment>--- Original</comment>';
        $diff[] = '<comment>+++ Modificado</comment>';
        
        $maxLines = max(count($originalLines), count($newLines));
        
        for ($i = 0; $i < $maxLines; $i++) {
            $originalLine = $originalLines[$i] ?? '';
            $newLine = $newLines[$i] ?? '';
            
            if ($originalLine !== $newLine) {
                if (isset($originalLines[$i])) {
                    $diff[] = "<fg=red>- " . htmlspecialchars($originalLine) . "</>";
                }
                
                if (isset($newLines[$i])) {
                    $diff[] = "<fg=green>+ " . htmlspecialchars($newLine) . "</>";
                }
            }
        }
        
        return $diff;
    }
} 