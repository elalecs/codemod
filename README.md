# CodeMod Tool 🛠️

Herramienta para modificación automática de código PHP mediante AST (Abstract Syntax Tree)

## TLDR: Instalación Rápida y Uso Básico 🚀

```bash
# Instalar
git clone https://github.com/tu-usuario/codemod.git
cd codemod
composer install
composer run build

# Uso básico (modificar un enum)
./codemod.phar enum:modify path/to/Enum.php --case=NEW_CASE --value=new_value

# Uso básico (añadir un trait a una clase)
./codemod.phar class:add-trait path/to/Class.php --trait=NombreDelTrait

# Ver todos los comandos disponibles
./codemod.phar list
```

## Propósito 🎯

Automatizar modificaciones comunes en código PHP durante migraciones o refactorizaciones:
- Añadir casos a Enums
- Agregar traits a clases
- Modificar propiedades de clases
- Actualizar arrays de configuración
- Añadir métodos a clases

## Requisitos 📋

- PHP 8.1+
- Composer
- Extensión PHAR

## Características Implementadas ✅

- Sistema básico de parsing de código
- Soporte para modificación de Enums
- Generación de PHAR ejecutable
- Modificador de clases (traits/propiedades/métodos)
- Sistema de backups automáticos
- Soporte para operaciones en lote (batch)
- Modo dry-run para pruebas sin aplicar cambios reales

## Guía de Comandos

### Opciones Globales

Todos los comandos aceptan estas opciones:

- `--dry-run`: Muestra los cambios sin aplicarlos
- `-v`, `-vv`, `-vvv`: Diferentes niveles de verbosidad

### Comandos para Enums

#### 1. Comando básico: `enum:modify`

```bash
# Añadir un único caso
./codemod.phar enum:modify path/to/Enum.php --case=NEW_CASE --value=new_value

# Añadir múltiples casos en un solo comando
./codemod.phar enum:modify path/to/Enum.php --cases="CASE1=value1,CASE2=value2"

# Añadir casos desde un archivo
./codemod.phar enum:modify path/to/Enum.php --cases-file=path/to/cases.txt
```

#### 2. Comando avanzado: `enum:batch-modify`

```bash
# Sintaxis PHP directa
./codemod.phar enum:batch-modify path/to/Enum.php --cases="case CASE1 = 'value1'; case CASE2 = 'value2';"

# Sintaxis multilinea
./codemod.phar enum:batch-modify path/to/Enum.php --cases-raw="
    case DRAFT = 'draft';
    case ACTIVE = 'active';
"
```

### Comandos para Clases

#### 1. Añadir Traits

```bash
# Añadir un único trait
./codemod.phar class:add-trait path/to/Class.php --trait=NombreDelTrait

# Añadir múltiples traits en una sola operación
./codemod.phar class:batch-add-traits path/to/Class.php --traits="Trait1,Trait2,Trait3"

# Añadir traits desde un archivo
./codemod.phar class:batch-add-traits path/to/Class.php --traits-file=path/to/traits.txt
```

#### 2. Añadir Propiedades

```bash
# Añadir una única propiedad
./codemod.phar class:add-property path/to/Class.php --name=propiedad --value="valor" --visibility=private --type=string

# Añadir múltiples propiedades en formato JSON
./codemod.phar class:batch-add-properties path/to/Class.php --properties='[
  {"name": "prop1", "value": "valor1", "visibility": "private", "type": "string"},
  {"name": "prop2", "value": "[]", "visibility": "protected", "type": "array"}
]'

# Añadir múltiples propiedades en formato PHP
./codemod.phar class:batch-add-properties path/to/Class.php --properties-raw="
  private string \$prop1 = 'valor1';
  protected array \$prop2 = [];
"
```

#### 3. Añadir Métodos

```bash
# Añadir un único método directamente
./codemod.phar class:add-method path/to/Class.php --method="public function nuevoMetodo() { return true; }"

# Añadir un método desde un archivo stub
./codemod.phar class:add-method path/to/Class.php --stub=path/to/method_stub.php

# Añadir múltiples métodos en formato PHP
./codemod.phar class:batch-add-methods path/to/Class.php --methods="
  public function metodo1(): string {
    return 'valor1';
  }
  
  public function metodo2(int \$param): void {
    echo \$param;
  }
"

# Añadir métodos desde un archivo
./codemod.phar class:batch-add-methods path/to/Class.php --methods-file=path/to/methods.php

# Añadir todos los métodos de un directorio de stubs
./codemod.phar class:batch-add-methods path/to/Class.php --directory=path/to/stubs/directory
```

#### 4. Modificar Propiedades

```bash
# Modificar una propiedad existente
./codemod.phar class:modify-property path/to/Class.php --name=propiedad --value="nuevo valor" --type=string
```

#### 5. Añadir Elementos a Arrays

```bash
# Añadir un elemento a un array
./codemod.phar class:add-to-array path/to/Class.php --property=nombreArray --key=clave --value="valor" --string
```

## Ejemplos de Uso Detallados

### Enums

#### Formato del archivo de casos (casos.txt):
```
ACTIVE = 'active'
INACTIVE = 'inactive'
PENDING = 'pending'
```

#### Ver cambios sin aplicarlos:
```bash
./codemod.phar enum:modify path/to/Enum.php --cases="ACTIVE=active,INACTIVE=inactive" --dry-run
```

### Propiedades

#### Añadir propiedades con tipos complejos:
```bash
./codemod.phar class:batch-add-properties path/to/Class.php --properties='[
  {"name": "apiKey", "value": "null", "visibility": "protected", "type": "string"},
  {"name": "lastLogin", "value": "null", "visibility": "public", "type": "?DateTime"},
  {"name": "settings", "value": "[]", "visibility": "private", "type": "array"}
]'
```

### Métodos

#### Contenido del archivo de método (method_stub.php):
```php
public function getFullName(): string
{
    return $this->firstName . ' ' . $this->lastName;
}
```

## Arquitectura 🏗️
```
src/
├── Parser/          # Analizador de código AST
├── Modifiers/       # Modificadores de código
├── FileHandler.php  # Manejo de archivos
├── CLI.php          # Punto de entrada CLI
└── Commands/        # Comandos disponibles
tests/
├── Feature/         # Pruebas de funcionalidad
└── Pest.php         # Configuración de PEST
```

## Próximos Pasos ⏳
- [ ] Usar un prettier para antes de guardar el PHP final
- [ ] Sistema de plugins
- [ ] Plantillas predefinidas para casos comunes de enums
- [ ] Generación automática de casos basados en patrones
- [ ] Mejorar modo dry-run con visualización de diferencias más avanzada

## Desarrollo y Contribución 🤝

### Comandos Útiles

```bash
# Construir PHAR
composer run build

# Debuggear comandos
./codemod.phar -vvv list

# Inspeccionar PHAR
php codemod.phar --info

# Ejecutar pruebas
php vendor/bin/pest
```

### Contribuir al Proyecto

1. Clonar repositorio
2. `composer install`
3. Hacer cambios en `src/`
4. Probar con `composer test`
5. Rebuild PHAR: `composer run build`

> **Importante**: Siempre verificar backups (.bak) después de cada modificación

## Licencia

Este proyecto está licenciado bajo la Licencia MIT.