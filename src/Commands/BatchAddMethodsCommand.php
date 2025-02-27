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

class BatchAddMethodsCommand extends Command
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
            ->setName('class:batch-add-methods')
            ->setDescription('Añadir múltiples métodos a una clase')
            ->addArgument('file', InputArgument::REQUIRED, 'Ruta al archivo de clase')
            ->addOption('methods', 'm', InputOption::VALUE_REQUIRED, 'Métodos en formato raw PHP')
            ->addOption('methods-file', 'f', InputOption::VALUE_REQUIRED, 'Archivo con definiciones de métodos')
            ->addOption('directory', 'd', InputOption::VALUE_REQUIRED, 'Directorio con archivos stub de métodos')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Muestra los cambios sin aplicarlos');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filePath = $input->getArgument('file');
        $methodsContent = $input->getOption('methods');
        $methodsFile = $input->getOption('methods-file');
        $directory = $input->getOption('directory');
        $isDryRun = $input->getOption('dry-run');
        
        if (!$methodsContent && !$methodsFile && !$directory) {
            throw new \InvalidArgumentException("Debe proporcionar --methods, --methods-file o --directory");
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
                $output->writeln("<e>No se encontró ninguna clase en el archivo especificado</e>");
                return Command::FAILURE;
            }
            
            // Procesar métodos
            $methods = [];
            
            if ($methodsContent) {
                $methods[] = $methodsContent;
            }
            
            if ($methodsFile) {
                if (!file_exists($methodsFile)) {
                    $output->writeln("<error>Archivo de métodos no encontrado: $methodsFile</error>");
                    return Command::FAILURE;
                }
                
                $methods[] = file_get_contents($methodsFile);
            }
            
            if ($directory) {
                if (!is_dir($directory)) {
                    $output->writeln("<error>Directorio de stubs no encontrado: $directory</error>");
                    return Command::FAILURE;
                }
                
                $stubFiles = glob($directory . '/*.php');
                foreach ($stubFiles as $stubFile) {
                    $methods[] = file_get_contents($stubFile);
                }
            }
            
            if (empty($methods)) {
                $output->writeln("<error>No se encontraron métodos válidos para añadir</error>");
                return Command::FAILURE;
            }
            
            // Preparar los métodos
            $parsedMethods = $this->parseMethods(implode("\n\n", $methods));
            
            if (empty($parsedMethods)) {
                $output->writeln("<error>No se pudieron extraer métodos válidos del contenido proporcionado</error>");
                return Command::FAILURE;
            }
            
            // Añadir los métodos a la clase
            $methodsAdded = 0;
            foreach ($methods as $methodCode) {
                // Limpiar el código del método si es necesario (eliminar etiquetas PHP, etc.)
                $methodCode = $this->cleanMethodCode($methodCode);
                
                // Extraer métodos individuales si hay varios en el mismo string
                $individualMethods = $this->parseMethods($methodCode);
                
                foreach ($individualMethods as $singleMethod) {
                    if ($this->modifier->addMethod($classNode, $singleMethod)) {
                        $methodsAdded++;
                        // Extraer el nombre del método para mostrarlo en el mensaje
                        if (preg_match('/function\s+(\w+)\s*\(/i', $singleMethod, $matches)) {
                            $methodName = $matches[1];
                            $output->writeln("<info>Método $methodName añadido</info>");
                        } else {
                            $output->writeln("<info>Método añadido</info>");
                        }
                    }
                }
            }
            
            if ($methodsAdded === 0) {
                $output->writeln("<comment>No se añadieron métodos (posiblemente ya existen en la clase)</comment>");
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
            $output->writeln("<info>Total de métodos añadidos: $methodsAdded</info>");
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $output->writeln("<error>Error: {$e->getMessage()}</error>");
            return Command::FAILURE;
        }
    }
    
    /**
     * Divide el contenido en métodos individuales
     */
    private function parseMethods(string $content): array
    {
        $methods = [];
        $content = trim($content);
        
        // Eliminar etiquetas PHP si existen
        $content = preg_replace('/<\?php|\?>/', '', $content);
        
        // Intentar extraer métodos usando expresiones regulares
        $pattern = '/\s*((?:public|private|protected|static)*\s+function\s+\w+\s*\([^)]*\)\s*(?:\:\s*\w+\s*)?(?:\{(?:[^{}]*|(?R))*\}))/s';
        preg_match_all($pattern, $content, $matches);
        
        if (!empty($matches[1])) {
            return $matches[1];
        }
        
        // Si no hay coincidencias, tratar el contenido completo como un solo método
        if (strpos($content, 'function') !== false) {
            return [$content];
        }
        
        return [];
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
    
    /**
     * Limpia el código del método eliminando etiquetas PHP y espacios innecesarios
     */
    private function cleanMethodCode(string $code): string
    {
        // Eliminar etiquetas PHP si existen
        $code = preg_replace('/<\?php|\?>/', '', $code);
        
        // Eliminar líneas vacías al principio y al final
        $code = trim($code);
        
        return $code;
    }
} 