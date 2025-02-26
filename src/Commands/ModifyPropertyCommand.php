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

class ModifyPropertyCommand extends Command
{
    private CodeParser $parser;
    private FileHandler $fileHandler;
    private ClassModifier $modifier;

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
        $this->setName('class:modify-property')
            ->setDescription('Modifica una propiedad existente en una clase PHP')
            ->addArgument('file', InputArgument::REQUIRED, 'Ruta al archivo de la clase')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Nombre de la propiedad a modificar')
            ->addOption('value', null, InputOption::VALUE_REQUIRED, 'Nuevo valor para la propiedad')
            ->addOption('type', null, InputOption::VALUE_OPTIONAL, 'Tipo de dato (string, int, bool, array, etc.)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filePath = $input->getArgument('file');
        $propertyName = $input->getOption('name');
        $propertyValue = $input->getOption('value');
        $type = $input->getOption('type');

        if (!$propertyName) {
            $output->writeln('<error>Debe especificar un nombre de propiedad con la opción --name</error>');
            return Command::FAILURE;
        }

        if ($propertyValue === null) {
            $output->writeln('<error>Debe especificar un valor para la propiedad con la opción --value</error>');
            return Command::FAILURE;
        }

        // Convertir el valor si es necesario
        if ($propertyValue === 'null') {
            $propertyValue = null;
        } elseif ($propertyValue === 'true') {
            $propertyValue = true;
        } elseif ($propertyValue === 'false') {
            $propertyValue = false;
        } elseif (is_numeric($propertyValue) && $type !== 'string') {
            $propertyValue = (int)$propertyValue;
        }
        // Si el tipo es string, no convertir el valor

        try {
            $code = $this->fileHandler->read($filePath);
            $ast = $this->parser->parse($code);
            
            $propertyModified = false;
            
            foreach ($ast as $node) {
                if ($node instanceof Class_) {
                    if ($this->modifier->modifyProperty($node, $propertyName, $propertyValue, $type)) {
                        $propertyModified = true;
                    }
                }
            }
            
            if (!$propertyModified) {
                $output->writeln("<error>No se encontró la propiedad '$propertyName' en ninguna clase del archivo</error>");
                return Command::FAILURE;
            }
            
            $newCode = $this->parser->generateCode($ast);
            $this->fileHandler->write($filePath, $newCode);
            
            $output->writeln("<info>Propiedad '$propertyName' modificada correctamente</info>");
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $output->writeln("<error>Error: {$e->getMessage()}</error>");
            return Command::FAILURE;
        }
    }
} 