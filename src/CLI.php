<?php

namespace CodeModTool;

use Symfony\Component\Console\Application;
use CodeModTool\Commands\ModifyEnumCommand;
use CodeModTool\Commands\BatchModifyEnumCommand;
use CodeModTool\Commands\AddTraitCommand;
use CodeModTool\Commands\BatchAddTraitsCommand;
use CodeModTool\Commands\AddPropertyCommand;
use CodeModTool\Commands\BatchAddPropertiesCommand;
use CodeModTool\Commands\ModifyPropertyCommand;
use CodeModTool\Commands\AddToArrayCommand;
use CodeModTool\Commands\AddMethodCommand;
use CodeModTool\Commands\BatchAddMethodsCommand;
use CodeModTool\Parser\CodeParser;
use CodeModTool\Modifiers\EnumModifier;
use CodeModTool\Modifiers\ClassModifier;

class CLI
{
    public function run(): int
    {
        $application = new Application('CodeMod Tool', '1.0.0');
        
        // Servicios compartidos
        $parser = new CodeParser();
        $fileHandler = new FileHandler();
        
        // Registrar comandos para enums
        $application->add(new ModifyEnumCommand(
            $parser,
            $fileHandler,
            new EnumModifier()
        ));
        
        $application->add(new BatchModifyEnumCommand(
            $parser,
            $fileHandler,
            new EnumModifier()
        ));
        
        // Comandos para modificar clases
        $classModifier = new ClassModifier();
        
        $application->add(new AddTraitCommand(
            $parser,
            $fileHandler,
            $classModifier
        ));
        
        $application->add(new BatchAddTraitsCommand(
            $parser,
            $fileHandler,
            $classModifier
        ));
        
        $application->add(new AddPropertyCommand(
            $parser,
            $fileHandler,
            $classModifier
        ));
        
        $application->add(new BatchAddPropertiesCommand(
            $parser,
            $fileHandler,
            $classModifier
        ));
        
        $application->add(new ModifyPropertyCommand(
            $parser,
            $fileHandler,
            $classModifier
        ));
        
        $application->add(new AddToArrayCommand(
            $parser,
            $fileHandler,
            $classModifier
        ));
        
        $application->add(new AddMethodCommand(
            $parser,
            $fileHandler,
            $classModifier
        ));
        
        $application->add(new BatchAddMethodsCommand(
            $parser,
            $fileHandler,
            $classModifier
        ));
        
        return $application->run();
    }
}
