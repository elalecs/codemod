<?php

namespace CodeModTool\Modifiers;

use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\EnumCase;
use PhpParser\Node\Scalar\String_;

class EnumModifier
{
    public function addCase(Enum_ $enum, string $name, string $value): void
    {
        $enum->stmts[] = new EnumCase(
            $name,
            new String_($value)
        );
    }
}
