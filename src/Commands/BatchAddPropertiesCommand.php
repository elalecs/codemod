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

class BatchAddPropertiesCommand extends Command
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
            ->setName('class:batch-add-properties')
            ->setDescription('Añadir múltiples propiedades a una clase')
            ->addArgument('file', InputArgument::REQUIRED, 'Ruta al archivo de clase')
            ->addOption('properties', 'p', InputOption::VALUE_REQUIRED, 'Propiedades en formato JSON')
            ->addOption('properties-file', 'f', InputOption::VALUE_REQUIRED, 'Archivo con definiciones de propiedades en formato JSON')
            ->addOption('properties-raw', 'r', InputOption::VALUE_REQUIRED, 'Propiedades en formato PHP raw')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Muestra los cambios sin aplicarlos');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filePath = $input->getArgument('file');
        $propertiesJson = $input->getOption('properties');
        $propertiesFile = $input->getOption('properties-file');
        $propertiesRaw = $input->getOption('properties-raw');
        $isDryRun = $input->getOption('dry-run');
        
        if (!$propertiesJson && !$propertiesFile && !$propertiesRaw) {
            throw new \InvalidArgumentException("Debe proporcionar --properties, --properties-file o --properties-raw");
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
            
            // Procesar propiedades
            $properties = [];
            
            if ($propertiesJson) {
                $properties = array_merge($properties, $this->parsePropertiesJson($propertiesJson, $output));
            }
            
            if ($propertiesFile) {
                if (!file_exists($propertiesFile)) {
                    $output->writeln("<error>Archivo de propiedades no encontrado: $propertiesFile</error>");
                    return Command::FAILURE;
                }
                
                $fileContent = file_get_contents($propertiesFile);
                $properties = array_merge($properties, $this->parsePropertiesJson($fileContent, $output));
            }
            
            if ($propertiesRaw) {
                $properties = array_merge($properties, $this->parsePropertiesRaw($propertiesRaw, $output));
            }
            
            if (empty($properties)) {
                $output->writeln("<error>No se encontraron propiedades válidas para añadir</error>");
                return Command::FAILURE;
            }
            
            // Añadir las propiedades a la clase
            $propertiesAdded = 0;
            foreach ($properties as $property) {
                if (isset($property['name'], $property['value'])) {
                    $visibilityStr = $property['visibility'] ?? 'private';
                    $type = $property['type'] ?? null;
                    
                    // Convertir cadena de visibilidad a constante int
                    $visibility = match(strtolower($visibilityStr)) {
                        'public' => Class_::MODIFIER_PUBLIC,
                        'protected' => Class_::MODIFIER_PROTECTED,
                        default => Class_::MODIFIER_PRIVATE
                    };
                    
                    $this->modifier->addProperty(
                        $classNode,
                        $property['name'],
                        $property['value'],
                        $visibility,
                        $type
                    );
                    
                    $propertiesAdded++;
                    $output->writeln("<info>Propiedad {$property['name']} añadida</info>");
                }
            }
            
            if ($propertiesAdded === 0) {
                $output->writeln("<comment>No se añadieron propiedades</comment>");
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
            $output->writeln("<info>Total de propiedades añadidas: $propertiesAdded</info>");
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $output->writeln("<error>Error: {$e->getMessage()}</error>");
            return Command::FAILURE;
        }
    }
    
    private function parsePropertiesJson(string $jsonContent, OutputInterface $output): array
    {
        $properties = [];
        
        try {
            $data = json_decode($jsonContent, true, 512, JSON_THROW_ON_ERROR);
            
            if (!is_array($data)) {
                $output->writeln("<comment>El JSON proporcionado no es un array válido</comment>");
                return [];
            }
            
            // Si es un array asociativo con una sola propiedad, convertirlo a array de propiedades
            if (isset($data['name']) && isset($data['value'])) {
                $data = [$data];
            }
            
            foreach ($data as $property) {
                if (isset($property['name']) && isset($property['value'])) {
                    $properties[] = $property;
                } else {
                    $output->writeln("<comment>Propiedad inválida, debe tener 'name' y 'value': " . json_encode($property) . "</comment>");
                }
            }
        } catch (\JsonException $e) {
            $output->writeln("<comment>Error al parsear JSON: {$e->getMessage()}</comment>");
        }
        
        return $properties;
    }
    
    private function parsePropertiesRaw(string $raw, OutputInterface $output): array
    {
        $properties = [];
        $lines = explode("\n", $raw);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Ignorar líneas vacías y comentarios
            if (empty($line) || $line[0] === '#' || strpos($line, '//') === 0) {
                continue;
            }
            
            // Intentar extraer definición de propiedad
            // Formato esperado: [visibility] [?type] $name = value;
            if (preg_match('/^(public|protected|private)?\s*(?:(\??\w+)\s+)?(\$\w+)\s*=\s*(.+?)\s*;?$/', $line, $matches)) {
                $visibility = !empty($matches[1]) ? $matches[1] : 'private';
                $type = !empty($matches[2]) ? $matches[2] : null;
                $name = substr($matches[3], 1); // Quitar el $ del nombre
                $value = $matches[4];
                
                // Limpiar posibles comillas del valor
                if (preg_match('/^[\'"](.+)[\'"]$/', $value, $valueMatch)) {
                    $value = $valueMatch[1];
                }
                
                $properties[] = [
                    'name' => $name,
                    'value' => $value,
                    'visibility' => $visibility,
                    'type' => $type
                ];
            }
            // Intentar otro formato más simple: $name = value;
            else if (preg_match('/^(\$\w+)\s*=\s*(.+?)\s*;?$/', $line, $matches)) {
                $name = substr($matches[1], 1); // Quitar el $ del nombre
                $value = $matches[2];
                
                // Limpiar posibles comillas del valor
                if (preg_match('/^[\'"](.+)[\'"]$/', $value, $valueMatch)) {
                    $value = $valueMatch[1];
                }
                
                $properties[] = [
                    'name' => $name,
                    'value' => $value,
                    'visibility' => 'private'
                ];
            }
        }
        
        return $properties;
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