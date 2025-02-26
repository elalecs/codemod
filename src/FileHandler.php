<?php

namespace CodeModTool;

class FileHandler
{
    public function read(string $path): string
    {
        if (!file_exists($path)) {
            throw new \RuntimeException("File not found: $path");
        }
        
        return file_get_contents($path);
    }

    public function write(string $path, string $content): void
    {
        $backupPath = $path . '.bak';
        if (!file_exists($backupPath)) {
            copy($path, $backupPath);
        }
        
        file_put_contents($path, $content);
    }
}
