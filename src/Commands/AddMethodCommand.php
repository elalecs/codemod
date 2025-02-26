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

class AddMethodCommand extends Command
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
        $this->setName('class:add-method')
            ->setDescription('Añade un método a una clase PHP desde un stub')
            ->addArgument('file', InputArgument::REQUIRED, 'Ruta al archivo de la clase')
            ->addOption('stub', null, InputOption::VALUE_REQUIRED, 'Ruta al archivo stub con el método')
            ->addOption('method', null, InputOption::VALUE_REQUIRED, 'Código del método a añadir (alternativa al stub)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filePath = $input->getArgument('file');
        $stubPath = $input->getOption('stub');
        $methodCode = $input->getOption('method');

        // Verificar que se ha proporcionado al menos una fuente para el método
        if (!$stubPath && !$methodCode) {
            $output->writeln('<error>Debe especificar un archivo stub (--stub) o el código del método (--method)</error>');
            return Command::FAILURE;
        }

        // Si se proporciona un archivo stub, leer su contenido
        if ($stubPath) {
            try {
                if (!file_exists($stubPath)) {
                    $output->writeln("<error>El archivo stub no existe: $stubPath</error>");
                    return Command::FAILURE;
                }
                $methodCode = file_get_contents($stubPath);
            } catch (\Exception $e) {
                $output->writeln("<error>Error al leer el archivo stub: {$e->getMessage()}</error>");
                return Command::FAILURE;
            }
        }

        try {
            $code = $this->fileHandler->read($filePath);
            $ast = $this->parser->parse($code);
            
            $methodAdded = false;
            
            foreach ($ast as $node) {
                if ($node instanceof Class_) {
                    if ($this->modifier->addMethod($node, $methodCode)) {
                        $methodAdded = true;
                    }
                }
            }
            
            if (!$methodAdded) {
                $output->writeln("<error>No se pudo añadir el método a la clase. Verifique que el código del método es válido</error>");
                return Command::FAILURE;
            }
            
            $newCode = $this->parser->generateCode($ast);
            $this->fileHandler->write($filePath, $newCode);
            
            $output->writeln("<info>Método añadido correctamente a la clase</info>");
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $output->writeln("<error>Error: {$e->getMessage()}</error>");
            return Command::FAILURE;
        }
    }
} 