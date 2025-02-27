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

class BatchModifyEnumCommand extends Command
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
        $this->setName('enum:batch-modify')
            ->setDescription('Add multiple cases to an enum with a simpler syntax')
            ->addArgument('file', InputArgument::REQUIRED, 'Path to enum file')
            ->addOption('cases', 'c', InputOption::VALUE_REQUIRED, 'Raw cases definition in format "case CASE1 = \'value1\'; case CASE2 = \'value2\';"')
            ->addOption('cases-raw', 'r', InputOption::VALUE_REQUIRED, 'Raw cases definition, allows multiline input with heredoc');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filePath = $input->getArgument('file');
        $casesOption = $input->getOption('cases');
        $casesRawOption = $input->getOption('cases-raw');
        
        // Use one of the provided options
        $casesContent = $casesOption ?? $casesRawOption ?? null;
        
        if (!$casesContent) {
            $output->writeln("<error>You must provide either --cases or --cases-raw option</error>");
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
            
            // Process cases from the raw content
            $casesArray = $this->parseCasesFromRawContent($casesContent);
            
            if (empty($casesArray)) {
                $output->writeln("<error>No valid cases found in the provided content</error>");
                return Command::FAILURE;
            }
            
            $casesAdded = 0;
            foreach ($casesArray as $name => $value) {
                $this->modifier->addCase($enumNode, $name, $value);
                $casesAdded++;
                $output->writeln("<info>Case $name added</info>");
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
     * Parse cases from raw content (similar to what would be written in a PHP file)
     */
    private function parseCasesFromRawContent(string $content): array
    {
        $cases = [];
        
        // Remove potential PHP tags
        $content = preg_replace('/<\?php|\?>/', '', $content);
        
        // Extract cases using regex
        preg_match_all('/case\s+([A-Z0-9_]+)\s*=\s*[\'"](.*?)[\'"]\s*;?/m', $content, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $cases[$match[1]] = $match[2];
        }
        
        // If no matches found with 'case' keyword, try simple format
        if (empty($cases)) {
            // Try KEY = 'value' format
            preg_match_all('/([A-Z0-9_]+)\s*=\s*[\'"](.*?)[\'"]\s*;?/m', $content, $matches, PREG_SET_ORDER);
            
            foreach ($matches as $match) {
                $cases[$match[1]] = $match[2];
            }
        }
        
        return $cases;
    }
} 