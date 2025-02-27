<?php

namespace CodeModTool\Commands;

use CodeModTool\FileHandler;
use CodeModTool\Modifiers\EnumModifier;
use CodeModTool\Parser\CodeParser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\CloningVisitor;

class ModifyEnumCommand extends Command
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
        $this->setName('enum:modify')
            ->setDescription('Add one or multiple cases to an enum')
            ->addArgument('file', InputArgument::REQUIRED, 'Path to enum file')
            ->addOption('case', null, InputOption::VALUE_REQUIRED, 'Case name (for single case)')
            ->addOption('value', null, InputOption::VALUE_REQUIRED, 'Case value (for single case)')
            ->addOption('cases', null, InputOption::VALUE_REQUIRED, 'Multiple cases in format "CASE1=value1,CASE2=value2"')
            ->addOption('cases-file', null, InputOption::VALUE_REQUIRED, 'Path to file containing cases (one per line in format "CASE=value")');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filePath = $input->getArgument('file');
        $caseName = $input->getOption('case');
        $caseValue = $input->getOption('value');
        $casesString = $input->getOption('cases');
        $casesFile = $input->getOption('cases-file');
        
        // Validate inputs
        if (!$caseName && !$caseValue && !$casesString && !$casesFile) {
            $output->writeln("<error>You must provide either --case and --value, --cases, or --cases-file</error>");
            return Command::FAILURE;
        }
        
        try {
            $code = $this->fileHandler->read($filePath);
            $ast = $this->parser->parse($code);
            
            $traverser = new NodeTraverser();
            $traverser->addVisitor(new CloningVisitor());
            $modifiedAst = $traverser->traverse($ast);
            
            // Find enum node
            $enumNode = null;
            foreach ($modifiedAst as $node) {
                if ($node instanceof \PhpParser\Node\Stmt\Enum_) {
                    $enumNode = $node;
                    break;
                }
            }
            
            if (!$enumNode) {
                $output->writeln("<error>No enum found in the specified file</error>");
                return Command::FAILURE;
            }
            
            // Process cases
            $casesAdded = 0;
            
            // Add single case if provided
            if ($caseName && $caseValue) {
                $this->modifier->addCase($enumNode, $caseName, $caseValue);
                $casesAdded++;
                $output->writeln("<info>Case $caseName added</info>");
            }
            
            // Process multiple cases from string
            if ($casesString) {
                $casesArray = $this->parseCasesString($casesString);
                foreach ($casesArray as $name => $value) {
                    $this->modifier->addCase($enumNode, $name, $value);
                    $casesAdded++;
                    $output->writeln("<info>Case $name added</info>");
                }
            }
            
            // Process cases from file
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
            
            if ($casesAdded === 0) {
                $output->writeln("<error>No cases were added</error>");
                return Command::FAILURE;
            }

            $newCode = $this->parser->generateCode($modifiedAst);
            $this->fileHandler->write($filePath, $newCode);
            
            $output->writeln("<info>Total cases added: $casesAdded</info>");
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $output->writeln("<error>Error: {$e->getMessage()}</error>");
            return Command::FAILURE;
        }
    }
    
    /**
     * Parse cases string in format "CASE1=value1,CASE2=value2"
     */
    private function parseCasesString(string $casesString): array
    {
        $cases = [];
        $pairs = explode(',', $casesString);
        
        foreach ($pairs as $pair) {
            $pair = trim($pair);
            if (empty($pair)) continue;
            
            // Support for both CASE=value and CASE='value' formats
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
} 