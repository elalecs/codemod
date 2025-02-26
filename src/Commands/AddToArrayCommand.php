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

class AddToArrayCommand extends Command
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
        $this->setName('class:add-to-array')
            ->setDescription('Añade un elemento a una propiedad de tipo array en una clase PHP')
            ->addArgument('file', InputArgument::REQUIRED, 'Ruta al archivo de la clase')
            ->addOption('property', null, InputOption::VALUE_REQUIRED, 'Nombre de la propiedad array')
            ->addOption('key', null, InputOption::VALUE_OPTIONAL, 'Clave para el elemento (opcional para arrays indexados)')
            ->addOption('value', null, InputOption::VALUE_REQUIRED, 'Valor a añadir al array')
            ->addOption('string', null, InputOption::VALUE_NONE, 'Tratar el valor como string aunque sea numérico');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filePath = $input->getArgument('file');
        $propertyName = $input->getOption('property');
        $key = $input->getOption('key');
        $value = $input->getOption('value');
        $forceString = $input->getOption('string');

        if (!$propertyName) {
            $output->writeln('<error>Debe especificar un nombre de propiedad con la opción --property</error>');
            return Command::FAILURE;
        }

        if ($value === null) {
            $output->writeln('<error>Debe especificar un valor con la opción --value</error>');
            return Command::FAILURE;
        }

        // Convertir el valor si es necesario
        if ($value === 'null') {
            $value = null;
        } elseif ($value === 'true') {
            $value = true;
        } elseif ($value === 'false') {
            $value = false;
        } elseif (is_numeric($value) && !$forceString) {
            $value = (int)$value;
        }
        // Si se especificó --string, no convertir el valor

        // Convertir la clave si es necesario
        if ($key !== null) {
            if (is_numeric($key) && !$forceString) {
                $key = (int)$key;
            }
        }

        try {
            $code = $this->fileHandler->read($filePath);
            $ast = $this->parser->parse($code);
            
            $elementAdded = false;
            
            foreach ($ast as $node) {
                if ($node instanceof Class_) {
                    if ($this->modifier->addToArrayProperty($node, $propertyName, $key, $value)) {
                        $elementAdded = true;
                    }
                }
            }
            
            if (!$elementAdded) {
                $output->writeln("<error>No se pudo añadir el elemento al array. Verifique que la propiedad '$propertyName' existe y es un array</error>");
                return Command::FAILURE;
            }
            
            $newCode = $this->parser->generateCode($ast);
            $this->fileHandler->write($filePath, $newCode);
            
            $output->writeln("<info>Elemento añadido correctamente al array '$propertyName'</info>");
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $output->writeln("<error>Error: {$e->getMessage()}</error>");
            return Command::FAILURE;
        }
    }
} 