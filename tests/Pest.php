<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

// uses(Tests\TestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Crea un archivo de clase de prueba
 */
function createTestClass(string $path = null): string
{
    // Usar directorio temporal si no se proporciona una ruta específica
    if ($path === null) {
        $path = tempnam(sys_get_temp_dir(), 'test_class_') . '.php';
    }
    
    file_put_contents($path, '<?php

class TestClass
{
    private string $name = \'Test\';
    protected array $options = array(\'option1\' => \'value1\', \'option2\' => \'value2\', \'option3\' => \'value3\');
    
    public function getName() : string
    {
        return $this->name;
    }
}
');
    
    return $path;
}

/**
 * Crea un archivo stub de método de prueba
 */
function createTestStub(string $content, string $path = null): string
{
    // Usar directorio temporal si no se proporciona una ruta específica
    if ($path === null) {
        $path = tempnam(sys_get_temp_dir(), 'test_stub_') . '.php';
    }
    
    file_put_contents($path, $content);
    
    return $path;
}

/**
 * Limpia los archivos de prueba
 */
function cleanupTestFiles(array $files): void
{
    foreach ($files as $file) {
        if (file_exists($file)) {
            unlink($file);
        }
        
        // También eliminar archivos de backup
        $bakFile = $file . '.bak';
        if (file_exists($bakFile)) {
            unlink($bakFile);
        }
    }
}

/**
 * Verifica si una clase contiene una propiedad específica
 */
function class_has_property(string $className, string $propertyName, ?string $type = null, ?string $visibility = null): bool
{
    if (!file_exists($className)) {
        return false;
    }
    
    $content = file_get_contents($className);
    
    // Construir el patrón regex según los parámetros proporcionados
    $visPattern = $visibility ? preg_quote($visibility) : '(public|protected|private)';
    $typePattern = $type ? preg_quote($type) : '[a-zA-Z0-9_\\\\]*';
    
    // Patrón más flexible para detectar propiedades
    // Acepta ambos formatos:
    // 1. private string $propName = valor;
    // 2. private $propName = valor;
    $pattern = "/{$visPattern}\s+(?:{$typePattern}\s+)?\\\${$propertyName}(?:\s*=|\s*;)/i";
    
    return preg_match($pattern, $content) === 1;
}

/**
 * Verifica si una clase contiene un método específico
 */
function class_has_method(string $className, string $methodName): bool
{
    if (!file_exists($className)) {
        return false;
    }
    
    $content = file_get_contents($className);
    
    // Buscar el método en el contenido del archivo
    $pattern = "/function\s+{$methodName}\s*\(/i";
    return preg_match($pattern, $content) === 1;
}

/**
 * Verifica si una clase usa un trait específico
 */
function class_uses_trait(string $className, string $traitName): bool
{
    if (!file_exists($className)) {
        return false;
    }
    
    $content = file_get_contents($className);
    
    // Buscar el uso del trait en el contenido del archivo
    $pattern = "/use\s+{$traitName};/i";
    return preg_match($pattern, $content) === 1;
} 