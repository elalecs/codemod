<?php

namespace CodeModTool;

use Symfony\Component\Console\Application;
use CodeModTool\Commands\EnumModifyCommand;
use CodeModTool\Commands\ClassModifyCommand;
use CodeModTool\Parser\CodeParser;
use CodeModTool\Modifiers\EnumModifier;
use CodeModTool\Modifiers\ClassModifier;

class CLI
{
    public function run(): int
    {
        $application = new Application('CodeMod Tool', '1.0.0');
        
        // Shared services
        $parser = new CodeParser();
        $fileHandler = new FileHandler();
        
        // Register unified command for enums
        $enumModifier = new EnumModifier();
        
        $application->add(new EnumModifyCommand(
            $parser,
            $fileHandler,
            $enumModifier
        ));
        
        // Commands to modify classes
        $classModifier = new ClassModifier();
        
        // Unified command for classes
        $application->add(new ClassModifyCommand(
            $parser,
            $fileHandler,
            $classModifier
        ));
        
        return $application->run();
    }
}
