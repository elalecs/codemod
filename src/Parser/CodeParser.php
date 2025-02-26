<?php

namespace CodeModTool\Parser;

use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;

class CodeParser
{
    public function parse(string $code): array
    {
        $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
        return $parser->parse($code);
    }

    public function generateCode(array $ast): string
    {
        $prettyPrinter = new Standard();
        return $prettyPrinter->prettyPrintFile($ast);
    }
}
