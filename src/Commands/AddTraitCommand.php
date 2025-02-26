<?php

namespace CodeModTool\Commands;

use CodeModTool\FileHandler;
use CodeModTool\Modifiers\ClassModifier;
use CodeModTool\Parser\CodeParser;
use PhpParser\Node\Stmt\Class_;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class AddTraitCommand extends BaseCommand
{
    public function __construct(
        CodeParser $parser,
        FileHandler $fileHandler,
        ClassModifier $modifier
    ) {
        parent::__construct($parser, $fileHandler, $modifier);
    }

    protected function configure()
    {
        parent::configure();
        
        $this->setName('class:add-trait')
            ->setDescription('Añade un trait a una clase PHP')
            ->addArgument('file', InputArgument::REQUIRED, 'Ruta al archivo de la clase')
            ->addOption('trait', null, InputOption::VALUE_REQUIRED, 'Nombre del trait a añadir (incluyendo namespace)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filePath = $input->getArgument('file');
        $traitName = $input->getOption('trait');

        if (!$traitName) {
            $output->writeln('<error>Debe especificar un trait con la opción --trait</error>');
            return Command::FAILURE;
        }

        try {
            $code = $this->fileHandler->read($filePath);
            $ast = $this->parser->parse($code);
            
            $classFound = false;
            
            foreach ($ast as $node) {
                if ($node instanceof Class_) {
                    $classFound = true;
                    $this->modifier->addTrait($node, $traitName);
                }
            }
            
            if (!$classFound) {
                $output->writeln('<error>No se encontró ninguna clase en el archivo</error>');
                return Command::FAILURE;
            }
            
            $newCode = $this->parser->generateCode($ast);
            
            // Usar el método de la clase base para manejar el modo dry-run
            $result = $this->writeChanges($input, $output, $filePath, $newCode, $code);
            
            if ($result === Command::SUCCESS && !$this->isDryRun) {
                $output->writeln("<info>Trait '$traitName' añadido correctamente a la clase</info>");
            }
            
            return $result;
            
        } catch (\Exception $e) {
            $output->writeln("<error>Error: {$e->getMessage()}</error>");
            return Command::FAILURE;
        }
    }
} 