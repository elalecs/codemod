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
./codemod.phar class:modify path/to/Class.php --trait=NombreDelTrait

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
- Añadir métodos a enums

## Requisitos 📋

- PHP 8.1+
- Composer
- Extensión PHAR

## Características Implementadas ✅

- Sistema básico de parsing de código
- Soporte para modificación de Enums
- Generación de PHAR ejecutable
- Modificador de clases (traits/propiedades/métodos)
- Modificador de enums (casos/métodos)
- Sistema de backups automáticos
- Soporte para operaciones en lote (batch)
- Modo dry-run para pruebas sin aplicar cambios reales

## Guía de Comandos

### Opciones Globales

Todos los comandos aceptan estas opciones:

- `--dry-run`: Muestra los cambios sin aplicarlos
- `-v`, `-vv`, `-vvv`: Diferentes niveles de verbosidad

### Comandos para Enums

#### Comando unificado `enum:modify`

El comando `enum:modify` permite realizar múltiples operaciones sobre un enum en una sola ejecución: añadir casos individuales, múltiples casos, métodos individuales o múltiples métodos.

```bash
# Ejemplo básico: añadir un caso simple
./codemod.phar enum:modify path/to/Enum.php --case=NEW_CASE --value=new_value

# Añadir múltiples casos
./codemod.phar enum:modify path/to/Enum.php --cases="CASE1=value1,CASE2=value2"

# Añadir casos desde un archivo
./codemod.phar enum:modify path/to/Enum.php --cases-file=path/to/cases.txt

# Añadir un método
./codemod.phar enum:modify path/to/Enum.php --method="public function nuevoMetodo() { return true; }"

# Añadir múltiples métodos usando heredoc
./codemod.phar enum:modify path/to/Enum.php --methods=$(cat << 'METODOS'
  public function metodo1(): string {
    return 'valor1';
  }
  
  public function metodo2(int \$param): void {
    echo \$param;
  }
METODOS
)

# Añadir métodos desde un archivo
./codemod.phar enum:modify path/to/Enum.php --methods-file=path/to/methods.php

# Combinar operaciones: añadir casos y métodos en una sola ejecución
./codemod.phar enum:modify path/to/Enum.php --cases="CASE1=value1,CASE2=value2" --method="public function getLabel(): string { return strtolower(\$this->name); }"
```

#### Formatos de entrada

**Casos:**
- Caso individual: `--case=NOMBRE --value=valor`
- Múltiples casos: `--cases="CASO1=valor1,CASO2=valor2"`
- Desde archivo: `--cases-file=path/to/file.txt` (formato: una línea por caso, `CASO=valor`)

**Métodos:**
- Método individual: `--method="public function nombre() { ... }"`
- Múltiples métodos: `--methods="public function nombre1() { ... } public function nombre2() { ... }"`
- Desde archivo: `--methods-file=path/to/methods.php` (contiene definiciones de métodos)

### Comandos para Clases

#### Comando unificado `class:modify`

El comando `class:modify` permite realizar múltiples operaciones sobre una clase en una sola ejecución: añadir traits, propiedades, métodos, modificar propiedades existentes y añadir elementos a arrays.

```bash
# Ejemplo básico: añadir un trait
./codemod.phar class:modify path/to/Class.php --trait=NombreDelTrait

# Añadir múltiples traits en una sola operación
./codemod.phar class:modify path/to/Class.php --traits="Trait1,Trait2,Trait3"

# Añadir múltiples traits con namespaces completos usando heredoc
./codemod.phar class:modify path/to/Class.php --traits=$(cat << 'TRAITS'
App\Traits\HasUuid,
App\Traits\HasTimestamps,
App\Traits\Searchable,
Illuminate\Database\Eloquent\SoftDeletes
TRAITS
)

# Añadir traits desde un archivo
./codemod.phar class:modify path/to/Class.php --traits-file=path/to/traits.txt

# Añadir una propiedad (formato: nombre:tipo=valor)
./codemod.phar class:modify path/to/Class.php --property="propiedad:string=valor"

# Añadir una propiedad sin tipo
./codemod.phar class:modify path/to/Class.php --property="propiedad=valor"

# Añadir múltiples propiedades en formato JSON
./codemod.phar class:modify path/to/Class.php --properties=$(cat << 'PROPERTIES'
[
  {"name":"apiKey","value":"null","visibility":"protected","type":"string"},
  {"name":"settings","value":"{\"timeout\": 30, \"retries\": 3}","visibility":"private","type":"array"}
]
PROPERTIES
)

# Añadir propiedades desde un archivo
./codemod.phar class:modify path/to/Class.php --properties-file=path/to/properties.json

# Modificar una propiedad existente
./codemod.phar class:modify path/to/Class.php --modify-property="propToModify" --new-value="nuevo valor" --new-type="string" --new-visibility="protected"

# Añadir elementos a un array
./codemod.phar class:modify path/to/Class.php --add-to-array="nombreArray" --key="clave" --array-value="valor" --string

# Añadir un método directamente
./codemod.phar class:modify path/to/Class.php --method="public function nuevoMetodo() { return true; }"

# Añadir múltiples métodos con heredoc
./codemod.phar class:modify path/to/Class.php --methods=$(cat << 'METODOS'
  public function metodo1(): string {
    return 'valor1';
  }
  
  public function metodo2(int \$param): void {
    echo \$param;
  }
METODOS
)

# Añadir métodos desde un archivo
./codemod.phar class:modify path/to/Class.php --methods-file=path/to/methods.php

# Añadir métodos desde un directorio (cada archivo contiene un método)
./codemod.phar class:modify path/to/Class.php --methods-dir=path/to/methods_directory

# Combinar operaciones: añadir traits, propiedades y métodos en una sola ejecución
./codemod.phar class:modify path/to/Class.php --traits="HasUuid,SoftDeletes" --property="status:string=active" --method="public function getStatus() { return \$this->status; }"
```

#### Formatos de entrada

**Traits:**
- Trait individual: `--trait=NombreDelTrait`
- Múltiples traits: `--traits="Trait1,Trait2,Trait3"`
- Desde archivo: `--traits-file=path/to/traits.txt` (formato: un trait por línea)

**Propiedades:**
- Propiedad individual: `--property="nombre:tipo=valor"` o `--property="nombre=valor"` (sin tipo)
- Múltiples propiedades: `--properties='[{"name":"prop1",...},{"name":"prop2",...}]'` (formato JSON)
- Desde archivo: `--properties-file=path/to/properties.json`

**Modificación de propiedades:**
- `--modify-property="nombrePropiedad" --new-value="nuevo valor" --new-type="string" --new-visibility="protected"`

**Añadir a arrays:**
- `--add-to-array="nombreArray" --key="clave" --array-value="valor" --string`

**Métodos:**
- Método individual: `--method="public function nombre() { ... }"`
- Múltiples métodos: `--methods="public function nombre1() { ... } public function nombre2() { ... }"`
- Desde archivo: `--methods-file=path/to/methods.php`
- Desde directorio: `--methods-dir=path/to/methods_directory`


### Modo Dry-Run (Simulación)

La opción `--dry-run` es una característica fundamental para probar cambios de forma segura:

- **¿Qué hace?** Muestra exactamente qué cambios se realizarían, pero sin modificar realmente los archivos
- **¿Por qué usarlo?** Previene errores, permite validar cambios antes de aplicarlos
- **¿Cómo funciona?** Ejecuta todo el proceso normal, pero detiene la escritura final al archivo

#### Ejemplos de Uso de Dry-Run

```bash
# Simular la adición de un caso a un enum
./codemod.phar enum:modify path/to/Enum.php --case=NEW_CASE --value=new_value --dry-run

# Probar la adición de un método a una clase
./codemod.phar class:modify path/to/Class.php --method="public function test() {}" --dry-run

# Verificar cómo quedarían múltiples propiedades antes de aplicar cambios
./codemod.phar class:modify path/to/Class.php --properties-file=props.json --dry-run

# Simular la adición de traits a varias clases
./codemod.phar class:modify "app/Models/*.php" --traits="SoftDeletes,HasUuid" --dry-run
```

#### Salida del Modo Dry-Run

Cuando se ejecuta con `--dry-run`, verás:

- Texto en color verde: Líneas que se añadirían
- Texto en color rojo: Líneas que se eliminarían
- Resumen de cambios que se aplicarían
- Mensaje "[MODO DRY-RUN] Cambios NO aplicados"

Este modo es especialmente útil cuando se trabaja con:
- Múltiples archivos a la vez
- Comandos batch que afectan muchos elementos
- Modificaciones complejas que requieren validación previa

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