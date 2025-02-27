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
            ->setDescription('Modify an enum: add cases and/or methods')
            ->addArgument('file', InputArgument::REQUIRED, 'Path to the enum file')
            
            // Individual case options
            ->addOption('case', null, InputOption::VALUE_REQUIRED, 'Name of the case to add')
            ->addOption('value', null, InputOption::VALUE_REQUIRED, 'Value of the case to add')
            
            // Multiple cases options
            ->addOption('cases', null, InputOption::VALUE_REQUIRED, 'Multiple cases in format "CASE1=value1,CASE2=value2"')
            ->addOption('cases-file', null, InputOption::VALUE_REQUIRED, 'File with cases to add (one per line in format "CASE=value")')
            
            // Method options
            ->addOption('method', null, InputOption::VALUE_REQUIRED, 'Method code to add')
            ->addOption('methods', null, InputOption::VALUE_REQUIRED, 'Multiple methods in pure PHP format')
            ->addOption('methods-file', null, InputOption::VALUE_REQUIRED, 'File with methods to add')
            
            // Simulation mode
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show changes without applying them');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filePath = $input->getArgument('file');
        $isDryRun = $input->getOption('dry-run');
        
        // Case options
        $caseName = $input->getOption('case');
        $caseValue = $input->getOption('value');
        $casesString = $input->getOption('cases');
        $casesFile = $input->getOption('cases-file');
        
        // Method options
        $methodCode = $input->getOption('method');
        $methodsContent = $input->getOption('methods');
        $methodsFile = $input->getOption('methods-file');
        
        // Verify that at least one option is provided
        if (!$caseName && !$casesString && !$casesFile && !$methodCode && !$methodsContent && !$methodsFile) {
            $output->writeln("<error>You must provide at least one option: --case, --cases, --cases-file, --method, --methods or --methods-file</error>");
            return Command::FAILURE;
        }
        
        // Validate that if --case is provided, --value is also provided
        if ($caseName && !$caseValue) {
            $output->writeln("<error>If --case is provided, --value must also be provided</error>");
            return Command::FAILURE;
        }
        
        try {
            // Read the code from the file
            $code = $this->fileHandler->read($filePath);
            $ast = $this->parser->parse($code);
            
            // Create a copy of the AST to work with
            $traverser = new NodeTraverser();
            $traverser->addVisitor(new CloningVisitor());
            $modifiedAst = $traverser->traverse($ast);
            
            // Find the enum node
            $enumNode = null;
            foreach ($modifiedAst as $node) {
                if ($node instanceof Enum_) {
                    $enumNode = $node;
                    break;
                }
            }
            
            if (!$enumNode) {
                $output->writeln("<error>No enum found in the specified file</error>");
                return Command::FAILURE;
            }
            
            // Counters to track changes
            $casesAdded = 0;
            $methodsAdded = 0;
            
            // Process cases
            if ($caseName && $caseValue) {
                $this->modifier->addCase($enumNode, $caseName, $caseValue);
                $casesAdded++;
                $output->writeln("<info>Case $caseName added</info>");
            }
            
            if ($casesString) {
                $casesArray = $this->parseCasesString($casesString);
                foreach ($casesArray as $name => $value) {
                    $this->modifier->addCase($enumNode, $name, $value);
                    $casesAdded++;
                    $output->writeln("<info>Case $name added</info>");
                }
            }
            
            if ($casesFile) {
                if (!file_exists($casesFile)) {
                    $output->writeln("<error>Cases file not found: $casesFile</error>");
                    return Command::FAILURE;
                }
                
                $casesContent = file_get_contents($casesFile);
                $casesArray = $this->parseCasesFromFileContent($casesContent);
                
                foreach ($casesArray as $name => $value) {
                    $this->modifier->addCase($enumNode, $name, $value);
                    $casesAdded++;
                    $output->writeln("<info>Case $name added</info>");
                }
            }
            
            // Process methods
            if ($methodCode) {
                if ($this->modifier->addMethod($enumNode, $methodCode)) {
                    $methodName = $this->extractMethodName($methodCode);
                    $methodsAdded++;
                    $output->writeln("<info>Method $methodName added</info>");
                } else {
                    $output->writeln("<error>Error adding the method</error>");
                }
            }
            
            if ($methodsContent) {
                $methods = $this->parseMethods($methodsContent);
                foreach ($methods as $method) {
                    if ($this->modifier->addMethod($enumNode, $method)) {
                        $methodName = $this->extractMethodName($method);
                        $methodsAdded++;
                        $output->writeln("<info>Method $methodName added</info>");
                    }
                }
            }
            
            if ($methodsFile) {
                if (!file_exists($methodsFile)) {
                    $output->writeln("<error>Methods file not found: $methodsFile</error>");
                    return Command::FAILURE;
                }
                
                $methodsContent = file_get_contents($methodsFile);
                $methods = $this->parseMethods($methodsContent);
                
                foreach ($methods as $method) {
                    if ($this->modifier->addMethod($enumNode, $method)) {
                        $methodName = $this->extractMethodName($method);
                        $methodsAdded++;
                        $output->writeln("<info>Method $methodName added</info>");
                    }
                }
            }
            
            // Check if any changes were made
            if ($casesAdded === 0 && $methodsAdded === 0) {
                $output->writeln("<error>No changes were made</error>");
                return Command::FAILURE;
            }
            
            // Generate the modified code
            $newCode = $this->parser->generateCode($modifiedAst);
            
            // Dry-run mode
            if ($isDryRun) {
                $output->writeln("<info>Dry-run mode: Showing changes without applying them</info>");
                $this->showDiff($output, $code, $newCode);
                $output->writeln("<info>Total of cases added: $casesAdded</info>");
                $output->writeln("<info>Total of methods added: $methodsAdded</info>");
                $output->writeln("<comment>[DRY-RUN MODE] Changes NOT applied</comment>");
                return Command::SUCCESS;
            }
            
            // Write the changes to the file
            $this->fileHandler->write($filePath, $newCode);
            
            // Show summary
            $output->writeln("<info>Total of cases added: $casesAdded</info>");
            $output->writeln("<info>Total of methods added: $methodsAdded</info>");
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $output->writeln("<error>Error: {$e->getMessage()}</error>");
            return Command::FAILURE;
        }
    }
    
    /**
     * Parse a cases string in format "CASE1=value1,CASE2=value2"
     */
    private function parseCasesString(string $casesString): array
    {
        $cases = [];
        $pairs = explode(',', $casesString);
        
        foreach ($pairs as $pair) {
            $pair = trim($pair);
            if (empty($pair)) continue;
            
            // Support for formats CASE=value and CASE='value'
            if (preg_match('/^([A-Z0-9_]+)=[\'"]{0,1}(.*?)[\'"]{0,1}$/', $pair, $matches)) {
                $cases[$matches[1]] = $matches[2];
            }
        }
        
        return $cases;
    }
    
    /**
     * Parse cases from file content (one per line)
     */
    private function parseCasesFromFileContent(string $content): array
    {
        $cases = [];
        $lines = explode("\n", $content);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || $line[0] === '#' || $line[0] === '/') continue; // Skip comments and empty lines
            
            // Support for various formats:
            // CASE = 'value'
            // CASE='value'
            // case CASE = 'value';
            if (preg_match('/(?:case\s+)?([A-Z0-9_]+)\s*=\s*[\'"]{0,1}(.*?)[\'"]{0,1}\s*;?$/', $line, $matches)) {
                $cases[$matches[1]] = $matches[2];
            }
        }
        
        return $cases;
    }
    
    /**
     * Parse methods from a string or file content
     */
    private function parseMethods(string $content): array
    {
        $methods = [];
        
        // Remove PHP tag if it exists
        $content = preg_replace('/^\s*<\?php\s*/i', '', $content);
        
        // Find methods based on function declaration
        preg_match_all('/\s*(public|private|protected)?\s*(static)?\s*function\s+(\w+)\s*\([^)]*\)\s*(?::\s*[^{]+)?\s*\{(?:[^{}]|(?R))*\}/s', $content, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $methods[] = trim($match[0]);
        }
        
        return $methods;
    }
    
    /**
     * Extract the method name from its code
     */
    private function extractMethodName(string $methodCode): string
    {
        preg_match('/function\s+(\w+)\s*\(/i', $methodCode, $matches);
        return $matches[1] ?? 'unknown';
    }
    
    /**
     * Show the differences between two code blocks
     */
    private function showDiff(OutputInterface $output, string $original, string $modified): void
    {
        $output->writeln('<info>[DRY-RUN MODE] Proposed changes:</info>');
        
        $differ = new \SebastianBergmann\Diff\Differ(new \SebastianBergmann\Diff\Output\StrictUnifiedDiffOutputBuilder([
            'collapseRanges'      => true,
            'commonLineThreshold' => 6,
            'contextLines'        => 3,
            'fromFile'            => 'Original',
            'toFile'              => 'Modified',
        ]));

        $output->writeln($differ->diff($original, $modified));
        $output->writeln('<info>[DRY-RUN MODE] Finished. No changes have been applied.</info>');
    }
} 