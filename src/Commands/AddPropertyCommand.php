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

class AddPropertyCommand extends Command
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
        $this->setName('class:add-property')
            ->setDescription('Añade una propiedad a una clase PHP')
            ->addArgument('file', InputArgument::REQUIRED, 'Ruta al archivo de la clase')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Nombre de la propiedad')
            ->addOption('value', null, InputOption::VALUE_OPTIONAL, 'Valor por defecto de la propiedad')
            ->addOption('visibility', null, InputOption::VALUE_OPTIONAL, 'Visibilidad (private, protected, public)', 'private')
            ->addOption('type', null, InputOption::VALUE_OPTIONAL, 'Tipo de dato (string, int, bool, array, etc.)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filePath = $input->getArgument('file');
        $propertyName = $input->getOption('name');
        $propertyValue = $input->getOption('value');
        $visibility = $input->getOption('visibility');
        $type = $input->getOption('type');

        if (!$propertyName) {
            $output->writeln('<error>Debe especificar un nombre de propiedad con la opción --name</error>');
            return Command::FAILURE;
        }

        // Convertir el valor si es necesario
        if ($propertyValue !== null) {
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
        }

        // Convertir la visibilidad a constante de Class_
        $visibilityMap = [
            'private' => Class_::MODIFIER_PRIVATE,
            'protected' => Class_::MODIFIER_PROTECTED,
            'public' => Class_::MODIFIER_PUBLIC
        ];

        if (!isset($visibilityMap[$visibility])) {
            $output->writeln('<error>Visibilidad no válida. Use private, protected o public</error>');
            return Command::FAILURE;
        }

        $visibilityConstant = $visibilityMap[$visibility];

        try {
            $code = $this->fileHandler->read($filePath);
            $ast = $this->parser->parse($code);
            
            $classFound = false;
            
            foreach ($ast as $node) {
                if ($node instanceof Class_) {
                    $classFound = true;
                    $this->modifier->addProperty($node, $propertyName, $propertyValue, $visibilityConstant, $type);
                }
            }
            
            if (!$classFound) {
                $output->writeln('<error>No se encontró ninguna clase en el archivo</error>');
                return Command::FAILURE;
            }
            
            $newCode = $this->parser->generateCode($ast);
            $this->fileHandler->write($filePath, $newCode);
            
            $output->writeln("<info>Propiedad '$propertyName' añadida correctamente a la clase</info>");
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $output->writeln("<error>Error: {$e->getMessage()}</error>");
            return Command::FAILURE;
        }
    }
} 