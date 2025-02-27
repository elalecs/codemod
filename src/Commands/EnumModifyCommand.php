<?php

namespace CodeModTool\Commands;

use CodeModTool\FileHandler;
use CodeModTool\Modifiers\EnumModifier;
use CodeModTool\Parser\CodeParser;
use PhpParser\Node\Stmt\Enum_;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\CloningVisitor;

class EnumModifyCommand extends Command
{
    public function __construct(
        private CodeParser $parser,
        private FileHandler $fileHandler,
        private EnumModifier $modifier
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('enum:modify')
            ->setDescription('Modificar un enum: añadir casos y/o métodos')
            ->addArgument('file', InputArgument::REQUIRED, 'Ruta al archivo del enum')
            
            // Opciones para casos individuales
            ->addOption('case', null, InputOption::VALUE_REQUIRED, 'Nombre del caso a añadir')
            ->addOption('value', null, InputOption::VALUE_REQUIRED, 'Valor del caso a añadir')
            
            // Opciones para múltiples casos
            ->addOption('cases', null, InputOption::VALUE_REQUIRED, 'Múltiples casos en formato "CASO1=valor1,CASO2=valor2"')
            ->addOption('cases-file', null, InputOption::VALUE_REQUIRED, 'Archivo con casos a añadir (uno por línea en formato "CASO=valor")')
            
            // Opciones para métodos
            ->addOption('method', null, InputOption::VALUE_REQUIRED, 'Código del método a añadir')
            ->addOption('methods', null, InputOption::VALUE_REQUIRED, 'Múltiples métodos en formato PHP puro')
            ->addOption('methods-file', null, InputOption::VALUE_REQUIRED, 'Archivo con métodos a añadir')
            
            // Modo simulación
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Mostrar cambios sin aplicarlos');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filePath = $input->getArgument('file');
        $isDryRun = $input->getOption('dry-run');
        
        // Opciones de casos
        $caseName = $input->getOption('case');
        $caseValue = $input->getOption('value');
        $casesString = $input->getOption('cases');
        $casesFile = $input->getOption('cases-file');
        
        // Opciones de métodos
        $methodCode = $input->getOption('method');
        $methodsContent = $input->getOption('methods');
        $methodsFile = $input->getOption('methods-file');
        
        // Verificar que se proporciona al menos una opción
        if (!$caseName && !$casesString && !$casesFile && !$methodCode && !$methodsContent && !$methodsFile) {
            $output->writeln("<e>Debe proporcionar al menos una opción: --case, --cases, --cases-file, --method, --methods o --methods-file</e>");
            return Command::FAILURE;
        }
        
        // Validar que si se proporciona --case, también se proporcione --value
        if ($caseName && !$caseValue) {
            $output->writeln("<e>Si se proporciona --case, también debe proporcionarse --value</e>");
            return Command::FAILURE;
        }
        
        try {
            // Leer el código del archivo
            $code = $this->fileHandler->read($filePath);
            $ast = $this->parser->parse($code);
            
            // Crear una copia del AST para trabajar con ella
            $traverser = new NodeTraverser();
            $traverser->addVisitor(new CloningVisitor());
            $modifiedAst = $traverser->traverse($ast);
            
            // Encontrar el nodo del enum
            $enumNode = null;
            foreach ($modifiedAst as $node) {
                if ($node instanceof Enum_) {
                    $enumNode = $node;
                    break;
                }
            }
            
            if (!$enumNode) {
                $output->writeln("<e>No se encontró ningún enum en el archivo especificado</e>");
                return Command::FAILURE;
            }
            
            // Contadores para seguimiento de cambios
            $casesAdded = 0;
            $methodsAdded = 0;
            
            // Procesamiento de casos
            if ($caseName && $caseValue) {
                $this->modifier->addCase($enumNode, $caseName, $caseValue);
                $casesAdded++;
                $output->writeln("<info>Caso $caseName agregado</info>");
            }
            
            if ($casesString) {
                $casesArray = $this->parseCasesString($casesString);
                foreach ($casesArray as $name => $value) {
                    $this->modifier->addCase($enumNode, $name, $value);
                    $casesAdded++;
                    $output->writeln("<info>Caso $name agregado</info>");
                }
            }
            
            if ($casesFile) {
                if (!file_exists($casesFile)) {
                    $output->writeln("<e>Archivo de casos no encontrado: $casesFile</e>");
                    return Command::FAILURE;
                }
                
                $casesContent = file_get_contents($casesFile);
                $casesArray = $this->parseCasesFromFileContent($casesContent);
                
                foreach ($casesArray as $name => $value) {
                    $this->modifier->addCase($enumNode, $name, $value);
                    $casesAdded++;
                    $output->writeln("<info>Caso $name agregado</info>");
                }
            }
            
            // Procesamiento de métodos
            if ($methodCode) {
                if ($this->modifier->addMethod($enumNode, $methodCode)) {
                    $methodName = $this->extractMethodName($methodCode);
                    $methodsAdded++;
                    $output->writeln("<info>Método $methodName agregado</info>");
                } else {
                    $output->writeln("<e>Error al añadir el método</e>");
                }
            }
            
            if ($methodsContent) {
                $methods = $this->parseMethods($methodsContent);
                foreach ($methods as $method) {
                    if ($this->modifier->addMethod($enumNode, $method)) {
                        $methodName = $this->extractMethodName($method);
                        $methodsAdded++;
                        $output->writeln("<info>Método $methodName agregado</info>");
                    }
                }
            }
            
            if ($methodsFile) {
                if (!file_exists($methodsFile)) {
                    $output->writeln("<e>Archivo de métodos no encontrado: $methodsFile</e>");
                    return Command::FAILURE;
                }
                
                $methodsContent = file_get_contents($methodsFile);
                $methods = $this->parseMethods($methodsContent);
                
                foreach ($methods as $method) {
                    if ($this->modifier->addMethod($enumNode, $method)) {
                        $methodName = $this->extractMethodName($method);
                        $methodsAdded++;
                        $output->writeln("<info>Método $methodName agregado</info>");
                    }
                }
            }
            
            // Verificar si se realizó algún cambio
            if ($casesAdded === 0 && $methodsAdded === 0) {
                $output->writeln("<e>No se realizó ningún cambio</e>");
                return Command::FAILURE;
            }
            
            // Generar el código modificado
            $newCode = $this->parser->generateCode($modifiedAst);
            
            // Modo dry-run
            if ($isDryRun) {
                $output->writeln("<info>Modo dry-run: Mostrando cambios sin aplicarlos</info>");
                $this->showDiff($output, $code, $newCode);
                $output->writeln("<info>Total de casos agregados: $casesAdded</info>");
                $output->writeln("<info>Total de métodos agregados: $methodsAdded</info>");
                $output->writeln("<comment>[MODO DRY-RUN] Cambios NO aplicados</comment>");
                return Command::SUCCESS;
            }
            
            // Escribir los cambios al archivo
            $this->fileHandler->write($filePath, $newCode);
            
            // Mostrar resumen
            $output->writeln("<info>Total de casos agregados: $casesAdded</info>");
            $output->writeln("<info>Total de métodos agregados: $methodsAdded</info>");
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $output->writeln("<e>Error: {$e->getMessage()}</e>");
            return Command::FAILURE;
        }
    }
    
    /**
     * Parsear una cadena de casos en formato "CASO1=valor1,CASO2=valor2"
     */
    private function parseCasesString(string $casesString): array
    {
        $cases = [];
        $pairs = explode(',', $casesString);
        
        foreach ($pairs as $pair) {
            $pair = trim($pair);
            if (empty($pair)) continue;
            
            // Soporte para formatos CASO=valor y CASO='valor'
            if (preg_match('/^([A-Z0-9_]+)=[\'"]{0,1}(.*?)[\'"]{0,1}$/', $pair, $matches)) {
                $cases[$matches[1]] = $matches[2];
            }
        }
        
        return $cases;
    }
    
    /**
     * Parsear casos desde el contenido de un archivo (uno por línea)
     */
    private function parseCasesFromFileContent(string $content): array
    {
        $cases = [];
        $lines = explode("\n", $content);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || $line[0] === '#' || $line[0] === '/') continue; // Saltar comentarios y líneas vacías
            
            // Soporte para varios formatos:
            // CASO = 'valor'
            // CASO='valor'
            // case CASO = 'valor';
            if (preg_match('/(?:case\s+)?([A-Z0-9_]+)\s*=\s*[\'"]{0,1}(.*?)[\'"]{0,1}\s*;?$/', $line, $matches)) {
                $cases[$matches[1]] = $matches[2];
            }
        }
        
        return $cases;
    }
    
    /**
     * Parsear métodos desde una cadena o contenido de archivo
     */
    private function parseMethods(string $content): array
    {
        $methods = [];
        
        // Eliminar etiqueta PHP si existe
        $content = preg_replace('/^\s*<\?php\s*/i', '', $content);
        
        // Buscar métodos basados en la declaración function
        preg_match_all('/\s*(public|private|protected)?\s*(static)?\s*function\s+(\w+)\s*\([^)]*\)\s*(?::\s*[^{]+)?\s*\{(?:[^{}]|(?R))*\}/s', $content, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $methods[] = trim($match[0]);
        }
        
        return $methods;
    }
    
    /**
     * Extraer el nombre de un método a partir de su código
     */
    private function extractMethodName(string $methodCode): string
    {
        preg_match('/function\s+(\w+)\s*\(/i', $methodCode, $matches);
        return $matches[1] ?? 'desconocido';
    }
    
    /**
     * Mostrar las diferencias entre dos bloques de código
     */
    private function showDiff(OutputInterface $output, string $original, string $modified): void
    {
        $output->writeln('<info>[MODO DRY-RUN] Cambios propuestos:</info>');
        
        $differ = new \SebastianBergmann\Diff\Differ(new \SebastianBergmann\Diff\Output\StrictUnifiedDiffOutputBuilder([
            'collapseRanges'      => true,
            'commonLineThreshold' => 6,
            'contextLines'        => 3,
            'fromFile'            => 'Original',
            'toFile'              => 'Modificado',
        ]));

        $output->writeln($differ->diff($original, $modified));
        $output->writeln('<info>[MODO DRY-RUN] Finalizado. No se han aplicado cambios.</info>');
    }
} 