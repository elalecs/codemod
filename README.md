# CodeMod Tool 🛠️

Tool for automatic PHP code modification using AST (Abstract Syntax Tree)

## TLDR: Quick Installation and Basic Usage 🚀

```bash
# Install
git clone https://github.com/your-username/codemod.git
cd codemod
composer install
composer run build

# Basic usage (modify an enum)
./codemod.phar enum:modify path/to/Enum.php --case=NEW_CASE --value=new_value

# Basic usage (add a trait to a class)
./codemod.phar class:modify path/to/Class.php --trait=TraitName

# Basic usage (add a trait to a class with import)
./codemod.phar class:modify path/to/Class.php --trait=TraitName --import=Namespace\\TraitName

# View all available commands
./codemod.phar list
```

## Purpose 🎯

Automate common PHP code modifications during migrations or refactorings:
- Add cases to Enums
- Add traits to classes
- Modify class properties
- Update configuration arrays
- Add methods to classes
- Add methods to enums

## Requirements 📋

- PHP 8.1+
- Composer
- PHAR Extension

## Implemented Features ✅

- Basic code parsing system
- Support for Enum modification
- Executable PHAR generation
- Class modifier (traits/properties/methods)
- Enum modifier (cases/methods)
- Automatic backup system
- Support for batch operations
- Dry-run mode for testing without applying real changes
- Support for imports when adding traits

## Command Guide

### Global Options

All commands accept these options:

- `--dry-run`: Shows changes without applying them
- `-v`, `-vv`, `-vvv`: Different verbosity levels

### Enum Commands

#### Unified command `enum:modify`

The `enum:modify` command allows multiple operations on an enum in a single execution: add individual cases, multiple cases, individual methods, or multiple methods.

```bash
# Basic example: add a simple case
./codemod.phar enum:modify path/to/Enum.php --case=NEW_CASE --value=new_value

# Add multiple cases
./codemod.phar enum:modify path/to/Enum.php --cases="CASE1=value1,CASE2=value2"

# Add cases from a file
./codemod.phar enum:modify path/to/Enum.php --cases-file=path/to/cases.txt

# Add a method
./codemod.phar enum:modify path/to/Enum.php --method="public function newMethod() { return true; }"

# Add multiple methods using heredoc
./codemod.phar enum:modify path/to/Enum.php --methods=$(cat << 'METHODS'
  public function method1(): string {
    return 'value1';
  }
  
  public function method2(int \$param): void {
    echo \$param;
  }
METHODS
)

# Add methods from a file
./codemod.phar enum:modify path/to/Enum.php --methods-file=path/to/methods.php

# Combine operations: add cases and methods in a single execution
./codemod.phar enum:modify path/to/Enum.php --cases="CASE1=value1,CASE2=value2" --method="public function getLabel(): string { return strtolower(\$this->name); }"

# Convert a pure enum to a backed enum with string type
./codemod.phar enum:modify path/to/Enum.php --type=string

# Convert a pure enum to a backed enum with int type
./codemod.phar enum:modify path/to/Enum.php --type=int

# Convert a pure enum to a backed enum and add cases in one operation
./codemod.phar enum:modify path/to/Enum.php --type=string --cases="CASE1=value1,CASE2=value2"
```

#### Input Formats

**Cases:**
- Individual case: `--case=NAME --value=value`
- Multiple cases: `--cases="CASE1=value1,CASE2=value2"`
- From file: `--cases-file=path/to/file.txt` (format: one line per case, `CASE=value`)

**Methods:**
- Individual method: `--method="public function name() { ... }"`
- Multiple methods: `--methods="public function name1() { ... } public function name2() { ... }"`
- From file: `--methods-file=path/to/methods.php` (contains method definitions)

**Enum Type:**
- Convert to backed enum: `--type=string` or `--type=int`
- When converting from pure enum to backed enum, existing cases without values will get default values based on the type

### Class Commands

#### Unified command `class:modify`

The `class:modify` command allows multiple operations on a class in a single execution: add traits, properties, methods, modify existing properties, and add elements to arrays.

```bash
# Basic example: add a trait
./codemod.phar class:modify path/to/Class.php --trait=TraitName

# Add a trait with import statement
./codemod.phar class:modify path/to/Class.php --trait=TraitName --import=App\\Traits\\TraitName

# Add multiple traits in a single operation
./codemod.phar class:modify path/to/Class.php --traits="Trait1,Trait2,Trait3"

# Add multiple traits with their imports
./codemod.phar class:modify path/to/Class.php --traits="Trait1,Trait2,Trait3" --imports="App\\Traits\\Trait1,App\\Traits\\Trait2,App\\Traits\\Trait3"

# Add multiple traits with full namespaces using heredoc
./codemod.phar class:modify path/to/Class.php --traits=$(cat << 'TRAITS'
App\Traits\HasUuid,
App\Traits\HasTimestamps,
App\Traits\Searchable,
Illuminate\Database\Eloquent\SoftDeletes
TRAITS
)

# Add traits from a file
./codemod.phar class:modify path/to/Class.php --traits-file=path/to/traits.txt

# Add traits from a file with imports from another file
./codemod.phar class:modify path/to/Class.php --traits-file=path/to/traits.txt --imports-file=path/to/imports.txt

# Behavior of trait imports
# By default, each trait is added in a separate 'use' statement within the class:
# use Trait1;
# use Trait2;
# use Trait3;

# When adding specific combinations of traits (like HasTranslations, Auditable, InteractsWithMedia), 
# the tool will automatically group them in a single 'use' statement:
# use HasFactory, HasUuids, SoftDeletes, HasTranslations, Auditable, InteractsWithMedia;

# Add a property (format: name:type=value)
./codemod.phar class:modify path/to/Class.php --property="property:string=value"

# Add a property without type
./codemod.phar class:modify path/to/Class.php --property="property=value"

# Add multiple properties in JSON format
./codemod.phar class:modify path/to/Class.php --properties=$(cat << 'PROPERTIES'
[
  {"name":"apiKey","value":"null","visibility":"protected","type":"string"},
  {"name":"settings","value":"{\"timeout\": 30, \"retries\": 3}","visibility":"private","type":"array"}
]
PROPERTIES
)

# Add array property with complex content
./codemod.phar class:modify path/to/Class.php --property="casts:array" --array-value="[
    'type' => \App\Enums\PropertyType::class,
    'status' => \App\Enums\PropertyStatus::class,
    'amenities' => 'array',
    'settings' => 'array',
    'metadata' => 'array',
]"

# Add multiple array properties (ejecute comandos separados para cada propiedad)
# Primero añadir propiedad casts
./codemod.phar class:modify path/to/Class.php --property="casts:array" --array-value="[
    'type' => \App\Enums\PropertyType::class,
    'status' => \App\Enums\PropertyStatus::class
]"

# Luego añadir propiedad translatable
./codemod.phar class:modify path/to/Class.php --property="translatable:array" --array-value="[
    'name', 'description', 'policies'
]"

# Add array property from a file
./codemod.phar class:modify path/to/Class.php --property="casts:array" --array-value="$(cat path/to/casts.txt)"

# Add properties from a file
./codemod.phar class:modify path/to/Class.php --properties-file=path/to/properties.json

# Modify an existing property
./codemod.phar class:modify path/to/Class.php --modify-property="propToModify" --new-value="new value" --new-type="string" --new-visibility="protected"

# Add elements to an array
./codemod.phar class:modify path/to/Class.php --add-to-array="arrayName" --key="key" --array-value="value" --string

# Add a method directly
./codemod.phar class:modify path/to/Class.php --method="public function newMethod() { return true; }"

# Add multiple methods with heredoc
./codemod.phar class:modify path/to/Class.php --methods=$(cat << 'METHODS'
  public function method1(): string {
    return 'value1';
  }
  
  public function method2(int \$param): void {
    echo \$param;
  }
METHODS
)

# Add methods from a file
./codemod.phar class:modify path/to/Class.php --methods-file=path/to/methods.php

# Add methods from a directory (each file contains a method)
./codemod.phar class:modify path/to/Class.php --methods-dir=path/to/methods_directory

# Combine operations: add traits, properties, and methods in a single execution
./codemod.phar class:modify path/to/Class.php --traits="HasUuid,SoftDeletes" --property="status:string=active" --method="public function getStatus() { return \$this->status; }"

## Modificar Clases

### Añadir Traits con Imports
```bash
php bin/codemod class:modify archivo.php \
  --traits="Trait1,Trait2" \
  --imports="App\Traits\Trait1,App\Traits\Trait2"
```

**Nuevas capacidades**:
- Los imports se añaden automáticamente dentro del namespace correspondiente
- Prevención de duplicados en imports
- Ordenamiento inteligente con imports existentes
- Soporte para múltiples traits en un solo comando

**Reglas de validación**:
- Cada trait DEBE tener un import correspondiente (el comando fallará si hay más traits que imports)
- Se pueden añadir imports sin traits asociados (útil para clases de utilidad o facades)
- Los imports sin traits se añaden al namespace pero no se utilizan en la clase
- El orden de los imports y traits debe coincidir (el primer trait usa el primer import, etc.)

Ejemplo multi-traits:
```bash
php bin/codemod class:modify User.php \
  --traits="HasRoles,LogsActivity" \
  --imports="Spatie\Permission\Traits\HasRoles,Spatie\Activitylog\Traits\LogsActivity"
```

Ejemplo imports sin traits:
```bash
php bin/codemod class:modify User.php \
  --traits="HasRoles" \
  --imports="Spatie\Permission\Traits\HasRoles,Illuminate\Support\Facades\Log,Illuminate\Support\Str"
```

Ejemplo con archivo de imports:
```bash
php bin/codemod class:modify User.php \
  --traits-file="traits.txt" \
  --imports-file="imports.txt"
```

Donde `traits.txt` contiene:
```
HasRoles
LogsActivity
```

Y `imports.txt` puede contener más imports que traits:
```
Spatie\Permission\Traits\HasRoles
Spatie\Activitylog\Traits\LogsActivity
Illuminate\Support\Facades\Log
Illuminate\Support\Str
```

#### Input Formats

**Traits:**
- Individual trait: `--trait=TraitName`
- Multiple traits: `--traits="Trait1,Trait2,Trait3"`
- From file: `--traits-file=path/to/traits.txt` (format: one trait per line)

**Properties:**
- Individual property: `--property="name:type=value"` or `--property="name=value"` (without type)
- Multiple properties: `--properties='[{"name":"prop1",...},{"name":"prop2",...}]'` (JSON format)
- From file: `--properties-file=path/to/properties.json`
- Array properties: `--property="name:array"` con `--array-value="[value1, value2]"` 
  > **Nota importante**: Solo puede usarse un `--array-value` por comando. Para múltiples propiedades array, ejecute comandos separados.

**Property Modification:**
- `--modify-property="propertyName" --new-value="new value" --new-type="string" --new-visibility="protected"`

**Add to Arrays:**
- `--add-to-array="arrayName" --key="key" --array-value="value" --string`

**Methods:**
- Individual method: `--method="public function name() { ... }"`
- Multiple methods: `--methods="public function name1() { ... } public function name2() { ... }"`
- From file: `--methods-file=path/to/methods.php`
- From directory: `--methods-dir=path/to/methods_directory`

### Dry-Run Mode (Simulation)

The `--dry-run` option is a fundamental feature for safely testing changes:

- **What does it do?** Shows exactly what changes would be made, but without actually modifying the files
- **Why use it?** Prevents errors, allows validating changes before applying them
- **How does it work?** Runs the entire normal process, but stops the final write to the file

#### Examples of Dry-Run Usage

```bash
# Simulate adding a case to an enum
./codemod.phar enum:modify path/to/Enum.php --case=NEW_CASE --value=new_value --dry-run

# Test adding a method to a class
./codemod.phar class:modify path/to/Class.php --method="public function test() {}" --dry-run

# Verify how multiple properties would look before applying changes
./codemod.phar class:modify path/to/Class.php --properties-file=props.json --dry-run

# Simulate adding traits to multiple classes
./codemod.phar class:modify "app/Models/*.php" --traits="SoftDeletes,HasUuid" --dry-run
```

#### Dry-Run Mode Output

When running with `--dry-run`, you'll see:

- Text in green: Lines that would be added
- Text in red: Lines that would be removed
- Summary of changes that would be applied
- Message "[DRY-RUN MODE] Changes NOT applied"

This mode is especially useful when working with:
- Multiple files at once
- Batch commands that affect many elements
- Complex modifications that require prior validation

## Advanced Examples for Class Modification

### Working with Array Properties

When you need to add or modify array properties with complex values, you have several options:

#### 1. Using Files for Array Values (Recommended)

```bash
# Add a casts property using a file
./codemod.phar class:modify path/to/Class.php \
  --property="casts:array" --array-value="$(cat path/to/casts.txt)"
```

Where `casts.txt` file contains the array definition:
```php
[
    'date' => 'datetime',
    'active' => 'boolean',
    'settings' => 'array',
    'type' => \App\Enums\MyType::class
]
```

#### 2. Using Separate Commands for Multiple Array Properties

```bash
# First add casts property
./codemod.phar class:modify path/to/Class.php --property="casts:array" --array-value="[
    'type' => \App\Enums\PropertyType::class,
    'status' => \App\Enums\PropertyStatus::class
]"

# Then add translatable property
./codemod.phar class:modify path/to/Class.php --property="translatable:array" --array-value="[
    'name', 'description', 'policies'
]"
```

#### 3. Batch Processing Using JSON

```bash
./codemod.phar class:modify path/to/Class.php --properties-file=properties.json
```

With `properties.json` file:
```json
[
  {
    "name": "casts",
    "type": "array",
    "value": {
      "date": "datetime", 
      "active": "boolean"
    },
    "visibility": "protected"
  },
  {
    "name": "translatable",
    "type": "array",
    "value": ["name", "description"],
    "visibility": "protected"
  }
]
```

### ⚠️ Common Issues and Solutions

1. **"Too many arguments" Error**:
   ```
   Too many arguments to "class:modify" command, expected arguments "file".
   ```
   **Solution**: Use separate commands for each array property as shown above.

2. **Strings vs. Arrays**:
   When your array contains numeric keys that should be treated as strings, use the `--string` option:
   ```bash
   ./codemod.phar class:modify path/to/Class.php \
     --add-to-array="codes" --key="404" --array-value="Not Found" --string
   ```

3. **Escaping Special Characters**:
   Use single quotes for values containing special characters and escape $ characters when using heredoc:
   ```bash
   ./codemod.phar class:modify path/to/Class.php --method=$(cat << 'EOT'
   public function getUrl(): string {
       return $this->baseUrl . '/api';
   }
   EOT
   )
   ```

## Architecture 🏗️
```
src/
├── Parser/          # AST code analyzer
├── Modifiers/       # Code modifiers
├── FileHandler.php  # File handling
├── CLI.php          # CLI entry point
└── Commands/        # Available commands
tests/
├── Feature/         # Functionality tests
└── Pest.php         # PEST configuration
```

## Development and Contribution 🤝

### Useful Commands

```bash
# Build PHAR
composer run build

# Debug commands
./codemod.phar -vvv list

# Inspect PHAR
php codemod.phar --info

# Run tests
php vendor/bin/pest
```

### Contributing to the Project

1. Clone repository
2. `composer install`
3. Make changes in `src/`
4. Test with `composer test`
5. Rebuild PHAR: `composer run build`

> **Important**: Always verify backups (.bak) after each modification

## License

This project is licensed under the MIT License.