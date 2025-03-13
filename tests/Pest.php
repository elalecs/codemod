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
 * Creates a test class file
 */
function createTestClass(string $path = null): string
{
    // Use temporary directory if no specific path is provided
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
 * Creates a test method stub file
 */
function createTestStub(string $content, string $path = null): string
{
    // Use temporary directory if no specific path is provided
    if ($path === null) {
        $path = tempnam(sys_get_temp_dir(), 'test_stub_') . '.php';
    }
    
    file_put_contents($path, $content);
    
    return $path;
}

/**
 * Cleans up test files
 */
function cleanupTestFiles(array $files): void
{
    foreach ($files as $file) {
        if (file_exists($file)) {
            unlink($file);
        }
        
        // Also remove backup files
        $bakFile = $file . '.bak';
        if (file_exists($bakFile)) {
            unlink($bakFile);
        }
    }
}

/**
 * Checks if a class contains a specific property
 */
function class_has_property(string $className, string $propertyName, ?string $type = null, ?string $visibility = null): bool
{
    if (!file_exists($className)) {
        return false;
    }
    
    $content = file_get_contents($className);
    
    // Build regex pattern according to provided parameters
    $visPattern = $visibility ? preg_quote($visibility) : '(public|protected|private)';
    $typePattern = $type ? preg_quote($type) : '[a-zA-Z0-9_\\\\]*';
    
    // More flexible pattern to detect properties
    // Accepts both formats:
    // 1. private string $propName = value;
    // 2. private $propName = value;
    $pattern = "/{$visPattern}\s+(?:{$typePattern}\s+)?\\\${$propertyName}(?:\s*=|\s*;)/i";
    
    return preg_match($pattern, $content) === 1;
}

/**
 * Checks if a class contains a specific method
 */
function class_has_method(string $className, string $methodName): bool
{
    if (!file_exists($className)) {
        return false;
    }
    
    $content = file_get_contents($className);
    
    // Search for the method in the file content
    $pattern = "/function\s+{$methodName}\s*\(/i";
    return preg_match($pattern, $content) === 1;
}

/**
 * Checks if a class uses a specific trait
 */
function class_uses_trait(string $className, string $traitName): bool
{
    if (!file_exists($className)) {
        return false;
    }
    
    $content = file_get_contents($className);
    
    // Search for the trait usage in the file content
    $pattern = "/use\s+{$traitName};/i";
    return preg_match($pattern, $content) === 1;
}

/**
 * Creates a test class file with namespace and imports
 */
function createTestClassWithNamespace(string $path = null): string
{
    // Use temporary directory if no specific path is provided
    if ($path === null) {
        $path = tempnam(sys_get_temp_dir(), 'test_class_') . '.php';
    }
    
    file_put_contents($path, '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [\'name\', \'email\', \'password\'];
    
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [\'password\', \'remember_token\'];
    
    /**
     * Get the attributes that should be cast.
     *
     * @return array
     */
    protected function casts(): array
    {
        return [\'email_verified_at\' => \'datetime\', \'password\' => \'hashed\'];
    }
}
');
    
    return $path;
}

/**
 * Creates a test class file with existing imports
 */
function createTestClassWithExistingImports(string $path = null): string
{
    // Use temporary directory if no specific path is provided
    if ($path === null) {
        $path = tempnam(sys_get_temp_dir(), 'test_class_imports_') . '.php';
    }
    
    file_put_contents($path, '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;

class User extends Authenticatable
{
    use HasFactory, Notifiable;
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [\'name\', \'email\', \'password\'];
    
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [\'password\', \'remember_token\'];
    
    /**
     * Get the attributes that should be cast.
     *
     * @return array
     */
    protected function casts(): array
    {
        return [\'email_verified_at\' => \'datetime\', \'password\' => \'hashed\'];
    }
}
');
    
    return $path;
}