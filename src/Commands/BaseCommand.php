<?php

namespace CodeModTool\Commands;

use CodeModTool\FileHandler;
use CodeModTool\Modifiers\ClassModifier;
use CodeModTool\Parser\CodeParser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

abstract class BaseCommand extends Command
{
    protected CodeParser $parser;
    protected FileHandler $fileHandler;
    protected ClassModifier $modifier;
    protected bool $isDryRun = false;

    public function __construct(
        CodeParser $parser,
        FileHandler $fileHandler,
        ClassModifier $modifier
    ) {
        parent::__construct();
        $this->parser = $parser;
        $this->fileHandler = $fileHandler;
        $this->modifier = $modifier;
    }

    protected function configure()
    {
        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Muestra los cambios que se realizarían sin aplicarlos realmente'
        );
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->isDryRun = $input->getOption('dry-run');
        
        if ($this->isDryRun) {
            $output->writeln('<info>Ejecutando en modo dry-run. No se realizarán cambios reales.</info>');
        }
    }

    /**
     * Escribe el contenido en el archivo o muestra los cambios en modo dry-run
     */
    protected function writeChanges(InputInterface $input, OutputInterface $output, string $filePath, string $newCode, string $originalCode): int
    {
        if ($this->isDryRun) {
            $output->writeln('<info>Cambios que se realizarían (modo dry-run):</info>');
            
            // Mostrar un diff simple
            $diff = $this->generateSimpleDiff($originalCode, $newCode);
            $output->writeln($diff);
            
            return Command::SUCCESS;
        }
        
        try {
            $this->fileHandler->write($filePath, $newCode);
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln("<error>Error al escribir el archivo: {$e->getMessage()}</error>");
            return Command::FAILURE;
        }
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
            } else {
                // Mostrar solo algunas líneas de contexto
                if ($i > 0 && $i < $maxLines - 1) {
                    $prevDiff = end($diff);
                    if (strpos($prevDiff, '-') === 0 || strpos($prevDiff, '+') === 0) {
                        $diff[] = "  " . $originalLine;
                    }
                } else {
                    $diff[] = "  " . $originalLine;
                }
            }
        }
        
        return $diff;
    }
} 