# CodeMod - PHP Modification Tool 🛠️

Tool for automatic PHP code modification using AST (Abstract Syntax Tree).

## Quick Installation 🚀

```bash
git clone https://github.com/your-username/codemod.git
cd codemod
composer install
composer box-install
composer build
```

### Development Setup

```bash
# For continued development
composer dev
```

## Main Commands 📋

| Command | Description |
|---------|-------------|
| `enum:modify` | Modifies PHP enumerations (cases, methods) |
| `class:modify` | Modifies PHP classes (traits, properties, methods) |
| `./codemod.phar list` | Shows all available commands |

## Global Options 🌐

| Option | Description |
|--------|-------------|
| `--dry-run` | Simulates changes without applying them |
| `-v`, `-vv`, `-vvv` | Verbosity levels |

## Arguments by Category 📑

### Enumeration Modification (enum:modify)

#### Cases
| Argument | Description | Example |
|-----------|-------------|---------|
| `--case` | Adds an individual case | `--case=NEW_CASE --value=new_value` |
| `--cases` | Adds multiple cases | `--cases="CASE1=value1,CASE2=value2"` |
| `--cases-file` | Cases from file | `--cases-file=path/to/cases.txt` |
| `--type` | Converts to typed enum | `--type=string` or `--type=int` |

#### Methods for Enums
| Argument | Description | Example |
|-----------|-------------|---------|
| `--method` | Adds a method | `--method="public function newMethod() { return true; }"` |
| `--methods` | Adds multiple methods | `--methods="public function m1() {} public function m2() {}"` |
| `--methods-file` | Methods from file | `--methods-file=path/to/methods.php` |

### Class Modification (class:modify)

#### Traits and Imports
| Argument | Description | Example |
|-----------|-------------|---------|
| `--trait` | Adds a trait | `--trait=TraitName` |
| `--traits` | Adds multiple traits | `--traits="Trait1,Trait2,Trait3"` |
| `--traits-file` | Traits from file | `--traits-file=path/to/traits.txt` |
| `--import` | Import for a trait | `--import=Namespace\\TraitName` |
| `--imports` | Imports for multiple traits | `--imports="NS\\Trait1,NS\\Trait2"` |
| `--imports-file` | Imports from file | `--imports-file=path/to/imports.txt` |

> **Important rules**:
> - Each trait MUST have a corresponding import
> - Imports can be added without associated traits
> - The command will fail if there are more traits than imports

#### Properties
| Argument | Description | Example |
|-----------|-------------|---------|
| `--property` | Adds a property | `--property="name:type=value"` or `--property="name=value"` |
| `--properties` | Multiple properties (JSON) | `--properties='[{"name":"prop1",...},{"name":"prop2",...}]'` |
| `--properties-file` | Properties from file | `--properties-file=path/to/properties.json` |
| `--array-value` | Value for array property | `--property="casts:array" --array-value="[...]"` |
| `--string` | Treats values as string | Useful for numeric keys in arrays |

#### Property Modification
| Argument | Description | Example |
|-----------|-------------|---------|
| `--modify-property` | Property to modify | `--modify-property="propName"` |
| `--new-value` | New value | `--new-value="new value"` |
| `--new-type` | New type | `--new-type="string"` |
| `--new-visibility` | New visibility | `--new-visibility="protected"` |

#### Arrays
| Argument | Description | Example |
|-----------|-------------|---------|
| `--add-to-array` | Array name | `--add-to-array="arrayName"` |
| `--key` | Key to add | `--key="key"` |
| `--array-value` | Value to add | `--array-value="value"` |

#### Methods for Classes
| Argument | Description | Example |
|-----------|-------------|---------|
| `--method` | Adds a method | `--method="public function newMethod() { return true; }"` |
| `--methods` | Adds multiple methods | `--methods="public function m1() {} public function m2() {}"` |
| `--methods-file` | Methods from file | `--methods-file=path/to/methods.php` |
| `--methods-dir` | Methods from directory | `--methods-dir=path/to/methods_directory` |

## Common Usage Examples 📝

### Enumerations

```bash
# Add a simple case
./codemod.phar enum:modify path/to/Enum.php --case=NEW_CASE --value=new_value

# Add multiple cases and a method
./codemod.phar enum:modify path/to/Enum.php --cases="CASE1=value1,CASE2=value2" \
  --method="public function getLabel(): string { return strtolower(\$this->name); }"

# Convert to typed enum and add cases
./codemod.phar enum:modify path/to/Enum.php --type=string \
  --cases="CASE1=value1,CASE2=value2"
```

### Classes

```bash
# Add a trait with its import
./codemod.phar class:modify path/to/Class.php --trait=TraitName \
  --import=App\\Traits\\TraitName

# Add multiple traits with their imports
./codemod.phar class:modify path/to/Class.php \
  --traits="Trait1,Trait2" \
  --imports="App\\Traits\\Trait1,App\\Traits\\Trait2"

# Add imports without associated traits
./codemod.phar class:modify path/to/Class.php \
  --imports="Illuminate\\Support\\Facades\\Log,Illuminate\\Support\\Str"

# Add a property
./codemod.phar class:modify path/to/Class.php \
  --property="status:string=active"

# Add an array property
./codemod.phar class:modify path/to/Class.php \
  --property="casts:array" \
  --array-value="['date' => 'datetime', 'active' => 'boolean']"

# Modify an existing property
./codemod.phar class:modify path/to/Class.php \
  --modify-property="status" --new-value="inactive" \
  --new-type="string" --new-visibility="protected"

# Add a method
./codemod.phar class:modify path/to/Class.php \
  --method="public function getStatus() { return \$this->status; }"

# Combined operation
./codemod.phar class:modify path/to/Class.php \
  --traits="HasUuid,SoftDeletes" \
  --property="status:string=active" \
  --method="public function getStatus() { return \$this->status; }"
```

## Simulation Mode (Dry-Run) 🔍

```bash
# Simulate changes without applying them
./codemod.phar class:modify path/to/Class.php --trait=TraitName --dry-run
```

## Common Issues and Solutions ⚠️

1. **"Too many arguments" Error**:
   - Use separate commands for each array property

2. **Strings vs. Arrays**:
   - Use `--string` for numeric keys that should be treated as strings
   ```bash
   ./codemod.phar class:modify path/to/Class.php \
     --add-to-array="codes" --key="404" --array-value="Not Found" --string
   ```

3. **Special characters**:
   - Use single quotes for values with special characters
   - Escape $ characters when using heredoc

## Requirements 📋

- PHP 8.1+
- Composer
- PHAR Extension
