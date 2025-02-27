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
        
        // Servicios compartidos
        $parser = new CodeParser();
        $fileHandler = new FileHandler();
        
        // Registrar comando unificado para enums
        $enumModifier = new EnumModifier();
        
        $application->add(new EnumModifyCommand(
            $parser,
            $fileHandler,
            $enumModifier
        ));
        
        // Comandos para modificar clases
        $classModifier = new ClassModifier();
        
        // Comando unificado para clases
        $application->add(new ClassModifyCommand(
            $parser,
            $fileHandler,
            $classModifier
        ));
        
        return $application->run();
    }
}
