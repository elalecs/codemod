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
            ->setDescription('Add a case to an enum')
            ->addArgument('file', InputArgument::REQUIRED, 'Path to enum file')
            ->addOption('case', null, InputOption::VALUE_REQUIRED, 'Case name')
            ->addOption('value', null, InputOption::VALUE_REQUIRED, 'Case value');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filePath = $input->getArgument('file');
        $caseName = $input->getOption('case');
        $caseValue = $input->getOption('value');

        try {
            $code = $this->fileHandler->read($filePath);
            $ast = $this->parser->parse($code);
            
            $traverser = new NodeTraverser();
            $traverser->addVisitor(new CloningVisitor());
            $modifiedAst = $traverser->traverse($ast);

            foreach ($modifiedAst as $node) {
                if ($node instanceof \PhpParser\Node\Stmt\Enum_) {
                    $this->modifier->addCase($node, $caseName, $caseValue);
                }
            }

            $newCode = $this->parser->generateCode($modifiedAst);
            $this->fileHandler->write($filePath, $newCode);
            
            $output->writeln("<info>Case $caseName added successfully!</info>");
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $output->writeln("<error>Error: {$e->getMessage()}</error>");
            return Command::FAILURE;
        }
    }
} 