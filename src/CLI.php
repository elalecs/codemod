<?php

namespace CodeModTool;

use Symfony\Component\Console\Application;
use CodeModTool\Commands\ModifyEnumCommand;
use CodeModTool\Commands\AddTraitCommand;
use CodeModTool\Commands\AddPropertyCommand;
use CodeModTool\Commands\ModifyPropertyCommand;
use CodeModTool\Commands\AddToArrayCommand;
use CodeModTool\Commands\AddMethodCommand;
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
        
        // Registrar comandos
        $application->add(new ModifyEnumCommand(
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
        
        $application->add(new AddPropertyCommand(
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
        
        return $application->run();
    }
}
