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
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\CloningVisitor;
use SebastianBergmann\Diff\Differ;
use SebastianBergmann\Diff\Output\StrictUnifiedDiffOutputBuilder;

class ClassModifyCommand extends Command
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
            ->setName('class:modify')
            ->setDescription('Modificar una clase: añadir traits, propiedades, métodos y más')
            ->addArgument('file', InputArgument::REQUIRED, 'Ruta al archivo de la clase')
            
            // Opciones para traits
            ->addOption('trait', null, InputOption::VALUE_REQUIRED, 'Nombre del trait a añadir (incluyendo namespace)')
            ->addOption('traits', null, InputOption::VALUE_REQUIRED, 'Lista de traits separados por coma para añadir')
            ->addOption('traits-file', null, InputOption::VALUE_REQUIRED, 'Archivo con traits a añadir (uno por línea)')
            
            // Opciones para propiedades
            ->addOption('property', null, InputOption::VALUE_REQUIRED, 'Definición de propiedad en formato "nombre:tipo=valor"')
            ->addOption('properties', null, InputOption::VALUE_REQUIRED, 'Múltiples propiedades en formato JSON o PHP')
            ->addOption('properties-file', null, InputOption::VALUE_REQUIRED, 'Archivo con propiedades a añadir')
            
            // Opciones para modificar propiedades
            ->addOption('modify-property', null, InputOption::VALUE_REQUIRED, 'Nombre de la propiedad a modificar')
            ->addOption('new-value', null, InputOption::VALUE_REQUIRED, 'Nuevo valor para la propiedad')
            ->addOption('new-type', null, InputOption::VALUE_REQUIRED, 'Nuevo tipo para la propiedad')
            ->addOption('new-visibility', null, InputOption::VALUE_REQUIRED, 'Nueva visibilidad para la propiedad (private, protected, public)')
            
            // Opciones para arrays
            ->addOption('add-to-array', null, InputOption::VALUE_REQUIRED, 'Nombre de la propiedad array a la que añadir un elemento')
            ->addOption('key', null, InputOption::VALUE_REQUIRED, 'Clave para el nuevo elemento del array')
            ->addOption('array-value', null, InputOption::VALUE_REQUIRED, 'Valor para el nuevo elemento del array')
            ->addOption('string', null, InputOption::VALUE_NONE, 'Tratar valores numéricos como strings para arrays')
            
            // Opciones para métodos
            ->addOption('method', null, InputOption::VALUE_REQUIRED, 'Código del método a añadir')
            ->addOption('methods', null, InputOption::VALUE_REQUIRED, 'Múltiples métodos en formato PHP puro')
            ->addOption('methods-file', null, InputOption::VALUE_REQUIRED, 'Archivo con métodos a añadir')
            ->addOption('methods-dir', null, InputOption::VALUE_REQUIRED, 'Directorio con archivos de métodos a añadir')
            
            // Modo simulación
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Mostrar cambios sin aplicarlos');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filePath = $input->getArgument('file');
        $isDryRun = $input->getOption('dry-run');
        
        if ($isDryRun) {
            $output->writeln('<info>Modo dry-run: Mostrando cambios sin aplicarlos</info>');
        }
        
        // Opciones de traits
        $traitName = $input->getOption('trait');
        $traitsString = $input->getOption('traits');
        $traitsFile = $input->getOption('traits-file');
        
        // Opciones de propiedades
        $propertyDefinition = $input->getOption('property');
        $propertiesContent = $input->getOption('properties');
        $propertiesFile = $input->getOption('properties-file');
        
        // Opciones para modificar propiedades
        $modifyPropertyName = $input->getOption('modify-property');
        $newValue = $input->getOption('new-value');
        $newType = $input->getOption('new-type');
        $newVisibility = $input->getOption('new-visibility');
        
        // Opciones para arrays
        $arrayPropertyName = $input->getOption('add-to-array');
        $arrayKey = $input->getOption('key');
        $arrayValue = $input->getOption('array-value');
        $treatAsString = $input->getOption('string');
        
        // Opciones para métodos
        $methodCode = $input->getOption('method');
        $methodsContent = $input->getOption('methods');
        $methodsFile = $input->getOption('methods-file');
        $methodsDir = $input->getOption('methods-dir');
        
        // Verificar si se proporcionó al menos una opción de modificación
        if (!$traitName && !$traitsString && !$traitsFile &&
            !$propertyDefinition && !$propertiesContent && !$propertiesFile &&
            !$modifyPropertyName &&
            !$arrayPropertyName &&
            !$methodCode && !$methodsContent && !$methodsFile && !$methodsDir) {
            $output->writeln('<error>Debe proporcionar al menos una opción para modificar la clase</error>');
            return Command::FAILURE;
        }
        
        try {
            // Verificar si el archivo existe
            if (!file_exists($filePath)) {
                $output->writeln("<error>El archivo '$filePath' no existe</error>");
                return Command::FAILURE;
            }
            
            // Leer el código original
            $originalCode = file_get_contents($filePath);
            
            // Parsear el código
            $ast = $this->parser->parse($originalCode);
            
            // Encontrar la clase en el AST
            $class = null;
            foreach ($ast as $node) {
                if ($node instanceof Class_) {
                    $class = $node;
                    break;
                }
            }
            
            if (!$class) {
                $output->writeln("<error>No se encontró una clase en el archivo '$filePath'</error>");
                return Command::FAILURE;
            }
            
            // Hacer una copia del AST original para posibles modificaciones
            $traverser = new NodeTraverser();
            $traverser->addVisitor(new CloningVisitor());
            $astCopy = $traverser->traverse($ast);
            
            // Encontrar la clase en la copia del AST
            $classCopy = null;
            foreach ($astCopy as $node) {
                if ($node instanceof Class_) {
                    $classCopy = $node;
                    break;
                }
            }
            
            // Variables para contar cambios
            $traitsAdded = 0;
            $propertiesAdded = 0;
            $propertiesModified = 0;
            $arrayElementsAdded = 0;
            $methodsAdded = 0;
            
            // Procesar traits
            if ($traitName) {
                if ($this->modifier->addTrait($classCopy, $traitName)) {
                    $output->writeln("<info>Trait $traitName agregado</info>");
                    $traitsAdded++;
                } else {
                    $output->writeln("<comment>El trait $traitName ya existe o no pudo ser agregado</comment>");
                }
            }
            
            if ($traitsString) {
                $traits = array_map('trim', explode(',', $traitsString));
                foreach ($traits as $trait) {
                    if (empty($trait)) continue;
                    
                    if ($this->modifier->addTrait($classCopy, $trait)) {
                        $output->writeln("<info>Trait $trait agregado</info>");
                        $traitsAdded++;
                    } else {
                        $output->writeln("<comment>El trait $trait ya existe o no pudo ser agregado</comment>");
                    }
                }
            }
            
            if ($traitsFile && file_exists($traitsFile)) {
                $fileContent = file_get_contents($traitsFile);
                $traits = array_filter(array_map('trim', explode("\n", $fileContent)));
                
                foreach ($traits as $trait) {
                    if (empty($trait) || $trait[0] === '#' || $trait[0] === '/') continue; // Saltar comentarios
                    
                    if ($this->modifier->addTrait($classCopy, $trait)) {
                        $output->writeln("<info>Trait $trait agregado</info>");
                        $traitsAdded++;
                    } else {
                        $output->writeln("<comment>El trait $trait ya existe o no pudo ser agregado</comment>");
                    }
                }
            }
            
            // Procesar propiedades y arrays
            if ($propertyDefinition) {
                // Formato: nombre:tipo=valor
                if (str_contains($propertyDefinition, '=')) {
                    list($nameWithType, $valueString) = explode('=', $propertyDefinition, 2);
                    
                    // Extraer tipo si está presente
                    if (str_contains($nameWithType, ':')) {
                        list($name, $type) = explode(':', $nameWithType, 2);
                    } else {
                        $name = $nameWithType;
                        $type = null;
                    }
                    
                    // Convertir valor según el tipo
                    $value = $this->convertValue($valueString, $type);
                    
                    // Por defecto, visibilidad privada
                    $visibility = Class_::MODIFIER_PRIVATE;
                    
                    $this->modifier->addProperty($classCopy, $name, $value, $visibility, $type);
                    $output->writeln("<info>Propiedad $name añadida</info>");
                    $propertiesAdded++;
                } else {
                    $output->writeln("<error>Formato de propiedad inválido. Use 'nombre:tipo=valor'</error>");
                }
            }
            
            if ($propertiesContent) {
                $properties = $this->parseProperties($propertiesContent);
                foreach ($properties as $prop) {
                    $name = $prop['name'] ?? null;
                    $type = $prop['type'] ?? null;
                    $value = $prop['value'] ?? null;
                    $visibilityString = $prop['visibility'] ?? 'private';
                    
                    if (!$name) continue;
                    
                    // Convertir string de visibilidad a constante
                    $visibility = $this->getVisibilityConstant($visibilityString);
                    
                    $this->modifier->addProperty($classCopy, $name, $value, $visibility, $type);
                    $output->writeln("<info>Propiedad $name añadida</info>");
                    $propertiesAdded++;
                }
            }
            
            // Procesar propiedades desde archivo
            if ($propertiesFile) {
                if (!file_exists($propertiesFile)) {
                    $output->writeln("<e>Archivo de propiedades no encontrado: $propertiesFile</e>");
                    return Command::FAILURE;
                }
                
                $fileContent = file_get_contents($propertiesFile);
                $properties = $this->parsePropertiesJson($fileContent, $output);
                
                if (!empty($properties)) {
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
                                $classCopy,
                                $property['name'],
                                $property['value'],
                                $visibility,
                                $type
                            );
                            
                            $propertiesAdded++;
                            $output->writeln("<info>Propiedad {$property['name']} añadida desde archivo</info>");
                        }
                    }
                }
            }
            
            if ($modifyPropertyName) {
                // Verificar si se proporcionaron opciones para modificar
                if ($newValue !== null || $newType !== null || $newVisibility !== null) {
                    $value = $newValue !== null ? $this->convertValue($newValue, $newType) : null;
                    $type = $newType;
                    
                    // Convertir visibilidad a constante si se proporcionó
                    $visibilityConstant = null;
                    if ($newVisibility !== null) {
                        $visibilityConstant = $this->getVisibilityConstant($newVisibility);
                    }
                    
                    if ($this->modifier->modifyProperty($classCopy, $modifyPropertyName, $value, $type, $visibilityConstant)) {
                        $output->writeln("<info>Propiedad $modifyPropertyName modificada</info>");
                        $propertiesModified++;
                    } else {
                        $output->writeln("<error>No se encontró la propiedad $modifyPropertyName o no se pudo modificar</error>");
                    }
                } else {
                    $output->writeln("<error>Debe proporcionar al menos una opción para modificar la propiedad (--new-value, --new-type, --new-visibility)</error>");
                }
            }
            
            if ($arrayPropertyName && $arrayValue !== null) {
                // Convertir valor para el array
                if ($treatAsString && is_numeric($arrayValue)) {
                    $arrayValue = (string)$arrayValue;
                }
                
                if ($this->modifier->addToArrayProperty($classCopy, $arrayPropertyName, $arrayKey, $arrayValue)) {
                    $output->writeln("<info>Elemento agregado al array $arrayPropertyName</info>");
                    $arrayElementsAdded++;
                } else {
                    $output->writeln("<error>No se pudo añadir el elemento al array $arrayPropertyName. Verifique que la propiedad existe y es un array.</error>");
                }
            }
            
            // Procesar métodos
            if ($methodCode) {
                if ($this->modifier->addMethod($classCopy, $methodCode)) {
                    $methodName = $this->extractMethodName($methodCode);
                    $output->writeln("<info>Método $methodName agregado</info>");
                    $methodsAdded++;
                } else {
                    $output->writeln("<error>No se pudo añadir el método. Verifique la sintaxis del código.</error>");
                }
            }
            
            if ($methodsContent) {
                $methods = $this->parseMethods($methodsContent);
                
                foreach ($methods as $method) {
                    if ($this->modifier->addMethod($classCopy, $method)) {
                        $methodName = $this->extractMethodName($method);
                        $output->writeln("<info>Método $methodName agregado</info>");
                        $methodsAdded++;
                    } else {
                        $output->writeln("<error>No se pudo añadir un método. Verifique la sintaxis del código.</error>");
                    }
                }
            }
            
            // Procesar métodos desde archivo
            if ($methodsFile) {
                if (!file_exists($methodsFile)) {
                    $output->writeln("<e>Archivo de métodos no encontrado: $methodsFile</e>");
                    return Command::FAILURE;
                }
                
                $methodCode = file_get_contents($methodsFile);
                $individualMethods = $this->parseMethods($methodCode);
                
                foreach ($individualMethods as $singleMethod) {
                    if ($this->modifier->addMethod($classCopy, $singleMethod)) {
                        $methodsAdded++;
                        // Extraer el nombre del método para mostrarlo en el mensaje
                        if (preg_match('/function\s+(\w+)\s*\(/i', $singleMethod, $matches)) {
                            $methodName = $matches[1];
                            $output->writeln("<info>Método $methodName agregado desde archivo</info>");
                        } else {
                            $output->writeln("<info>Método agregado desde archivo</info>");
                        }
                    }
                }
            }
            
            if ($methodsDir && is_dir($methodsDir)) {
                $files = glob("$methodsDir/*.php");
                
                foreach ($files as $file) {
                    $methodContent = file_get_contents($file);
                    
                    if ($this->modifier->addMethod($classCopy, $methodContent)) {
                        $methodName = $this->extractMethodName($methodContent);
                        $output->writeln("<info>Método $methodName agregado desde " . basename($file) . "</info>");
                        $methodsAdded++;
                    } else {
                        $output->writeln("<error>No se pudo añadir el método desde " . basename($file) . ". Verifique la sintaxis del código.</error>");
                    }
                }
            }
            
            // Mostrar resumen de cambios
            if ($traitsAdded > 0) {
                $output->writeln("<info>Total de traits agregados: $traitsAdded</info>");
            }
            
            if ($propertiesAdded > 0) {
                $output->writeln("<info>Total de propiedades añadidas: $propertiesAdded</info>");
            }
            
            if ($propertiesModified > 0) {
                $output->writeln("<info>Total de propiedades modificadas: $propertiesModified</info>");
            }
            
            if ($arrayElementsAdded > 0) {
                $output->writeln("<info>Total de elementos agregados a arrays: $arrayElementsAdded</info>");
            }
            
            if ($methodsAdded > 0) {
                $output->writeln("<info>Total de métodos agregados: $methodsAdded</info>");
            }
            
            // Si no se hizo ningún cambio, mostrar mensaje
            if ($traitsAdded === 0 && $propertiesAdded === 0 && $propertiesModified === 0 && $arrayElementsAdded === 0 && $methodsAdded === 0) {
                $output->writeln("<comment>No se realizaron cambios en la clase</comment>");
                return Command::SUCCESS;
            }
            
            // Generar código modificado
            $modifiedCode = $this->parser->generateCode($astCopy);
            
            // En modo dry-run, mostrar las diferencias sin aplicar los cambios
            if ($isDryRun) {
                $output->writeln('<info>[MODO DRY-RUN] Cambios propuestos:</info>');
                $diff = $this->showDiff($originalCode, $modifiedCode);
                $output->writeln($diff);
                $output->writeln('<info>[MODO DRY-RUN] Cambios NO aplicados</info>');
            } else {
                // Aplicar los cambios
                // Crear backup
                file_put_contents("$filePath.bak", $originalCode);
                file_put_contents($filePath, $modifiedCode);
                $output->writeln("<info>Cambios aplicados a $filePath</info>");
                $output->writeln("<info>Backup guardado en $filePath.bak</info>");
            }
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $output->writeln("<error>Error: {$e->getMessage()}</error>");
            return Command::FAILURE;
        }
    }
    
    /**
     * Muestra las diferencias entre dos versiones de código
     */
    private function showDiff(string $originalCode, string $modifiedCode): string
    {
        $builder = new StrictUnifiedDiffOutputBuilder([
            'collapseRanges'      => true,
            'commonLineThreshold' => 6,
            'contextLines'        => 3,
            'fromFile'            => 'Original',
            'toFile'              => 'Modificado',
        ]);
        
        $differ = new Differ($builder);
        return $differ->diff($originalCode, $modifiedCode);
    }
    
    /**
     * Convierte un valor string al tipo adecuado
     */
    private function convertValue(string $valueString, ?string $type): mixed
    {
        // Eliminar comillas si están presentes
        if ((str_starts_with($valueString, '"') && str_ends_with($valueString, '"')) ||
            (str_starts_with($valueString, "'") && str_ends_with($valueString, "'"))) {
            $valueString = substr($valueString, 1, -1);
        }
        
        // Convertir según el tipo
        if ($type === 'bool' || $type === 'boolean') {
            return strtolower($valueString) === 'true' || $valueString === '1';
        } elseif ($type === 'int' || $type === 'integer') {
            return (int)$valueString;
        } elseif ($type === 'float' || $type === 'double') {
            return (float)$valueString;
        } elseif ($type === 'array') {
            return json_decode($valueString, true) ?? [];
        } else {
            // String u otro tipo
            return $valueString;
        }
    }
    
    /**
     * Convierte una string de visibilidad a la constante correspondiente
     */
    private function getVisibilityConstant(string $visibility): int
    {
        return match (strtolower($visibility)) {
            'public' => Class_::MODIFIER_PUBLIC,
            'protected' => Class_::MODIFIER_PROTECTED,
            default => Class_::MODIFIER_PRIVATE,
        };
    }
    
    /**
     * Extrae el nombre del método de una definición
     */
    private function extractMethodName(string $methodCode): string
    {
        if (preg_match('/function\s+([a-zA-Z0-9_]+)/i', $methodCode, $matches)) {
            return $matches[1];
        }
        return 'desconocido';
    }
    
    /**
     * Parsea propiedades desde una string en formato JSON o PHP
     */
    private function parseProperties(string $content): array
    {
        $content = trim($content);
        $properties = [];
        
        // Intentar parsear como JSON
        try {
            $json = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            if (is_array($json)) {
                // Si es un objeto JSON, usarlo directamente
                if (isset($json['name'])) {
                    $properties[] = $json;
                } else {
                    // Si es un array de objetos, usarlo como está
                    $properties = $json;
                }
            }
        } catch (\Exception $e) {
            // No es JSON válido, intentar parsear como PHP
            $lines = explode("\n", $content);
            
            foreach ($lines as $line) {
                $line = trim($line);
                
                // Saltar líneas vacías y comentarios
                if (empty($line) || $line[0] === '#' || $line[0] === '/' || $line[0] === '*') {
                    continue;
                }
                
                // Intentar extraer propiedades en formato:
                // [visibility] [type] $name = value;
                $pattern = '/\s*(public|private|protected)?\s*([a-zA-Z0-9_\\\]+)?\s*\$([a-zA-Z0-9_]+)\s*=\s*(.*?);/i';
                
                if (preg_match($pattern, $line, $matches)) {
                    $visibility = $matches[1] ?: 'private';
                    $type = $matches[2] ?: null;
                    $name = $matches[3];
                    $valueString = $matches[4];
                    
                    // Intentar evaluar el valor si es posible
                    try {
                        $value = eval("return $valueString;");
                    } catch (\Throwable $e) {
                        $value = $valueString;
                    }
                    
                    $properties[] = [
                        'name' => $name,
                        'type' => $type,
                        'value' => $value,
                        'visibility' => $visibility
                    ];
                }
            }
        }
        
        return $properties;
    }
    
    /**
     * Parsea métodos desde una string
     */
    private function parseMethods(string $content): array
    {
        $content = trim($content);
        $methods = [];
        
        // Si el contenido está vacío, devolver un array vacío
        if (empty($content)) {
            return [];
        }
        
        // Si el contenido comienza con <?php, eliminarlo para el procesamiento
        if (str_starts_with($content, '<?php')) {
            $content = preg_replace('/^\s*<\?php\s*/i', '', $content);
        }
        
        // Intentar extraer funciones individuales usando regex
        $pattern = '/\s*(public|private|protected|static)?\s*function\s+([a-zA-Z0-9_]+)\s*\([^{]*\)\s*(?::\s*[a-zA-Z0-9_\\\\]+)?\s*\{/i';
        preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE);
        
        if (!empty($matches[0])) {
            // Encontramos declaraciones de función
            $startPositions = array_column($matches[0], 1);
            $methodCount = count($startPositions);
            
            for ($i = 0; $i < $methodCount; $i++) {
                $startPos = $startPositions[$i];
                $endPos = ($i < $methodCount - 1) ? $startPositions[$i + 1] : strlen($content);
                
                // Extraer esta porción del contenido
                $methodContent = substr($content, $startPos);
                
                // Encontrar el cierre de la función (contando llaves)
                $bracesCount = 0;
                $foundEnd = false;
                $methodLength = 0;
                
                for ($j = 0; $j < strlen($methodContent); $j++) {
                    if ($methodContent[$j] === '{') {
                        $bracesCount++;
                    } elseif ($methodContent[$j] === '}') {
                        $bracesCount--;
                        if ($bracesCount === 0) {
                            $methodLength = $j + 1;
                            $foundEnd = true;
                            break;
                        }
                    }
                }
                
                if ($foundEnd) {
                    $methods[] = substr($methodContent, 0, $methodLength);
                }
            }
        }
        
        // Si no encontramos métodos con regex, tratar todo el contenido como un solo método
        if (empty($methods) && !empty($content)) {
            $methods[] = $content;
        }
        
        return $methods;
    }
    
    /**
     * Parsea propiedades desde formato JSON
     */
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
                if (isset($property['name'])) {
                    // Asegurarse de que value esté definido, incluso si es null
                    if (!isset($property['value'])) {
                        $property['value'] = null;
                    }
                    
                    // Si el valor es un array JSON, convertirlo a array PHP
                    if (is_array($property['value'])) {
                        // Ya es un array, no necesita conversión
                    } elseif (is_string($property['value']) && 
                             (str_starts_with(trim($property['value']), '[') || 
                              str_starts_with(trim($property['value']), '{'))) {
                        // Intentar decodificar como JSON si parece un array o objeto JSON
                        try {
                            $property['value'] = json_decode($property['value'], true, 512, JSON_THROW_ON_ERROR);
                        } catch (\JsonException $e) {
                            // Si falla, mantener el valor original
                        }
                    }
                    
                    $properties[] = $property;
                } else {
                    $output->writeln("<comment>Propiedad inválida, debe tener 'name': " . json_encode($property) . "</comment>");
                }
            }
        } catch (\JsonException $e) {
            $output->writeln("<comment>Error al parsear JSON: {$e->getMessage()}</comment>");
        }
        
        return $properties;
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