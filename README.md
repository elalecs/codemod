# CodeMod Tool 🛠️

Herramienta para modificación automática de código PHP mediante AST (Abstract Syntax Tree)

## Propósito 🎯
Automatizar modificaciones comunes en código PHP durante migraciones o refactorizaciones:
- Añadir casos a Enums
- Agregar traits a clases
- Modificar propiedades de clases
- Actualizar arrays de configuración

## Tecnologías Clave 🔑
- PHP-Parser (nikic/php-parser)
- Symfony Console
- PHAR packaging
- PEST Testing Framework

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

## Plan de Acción 📌

### Implementado ✅
- [x] Sistema básico de parsing de código
- [x] Soporte para modificación de Enums
- [x] Generación de PHAR ejecutable
- [x] Comando básico `enum:modify`
- [x] Modificador de clases (traits/propiedades)
- [x] Sistema de backups automáticos
- [x] Comandos para modificar clases
- [x] Crear pruebas PEST para cada comando, cada uso

### Próximos Pasos ⏳
- [ ] Modo dry-run para pruebas
- [ ] Usar un prettier para antes de guardar el PHP final
- [ ] Sistema de plugins

## Uso Básico 🚀
```bash
# Construir PHAR
composer run build

# Modificar un Enum
./codemod.phar enum:modify path/to/Enum.php \
  --case=NEW_CASE \
  --value=new_value

# Añadir un trait a una clase
./codemod.phar class:add-trait path/to/Class.php \
  --trait=NombreDelTrait

# Añadir una propiedad a una clase
./codemod.phar class:add-property path/to/Class.php \
  --name=nombrePropiedad \
  --value="valor por defecto" \
  --visibility=private \
  --type=string

# Modificar una propiedad existente
./codemod.phar class:modify-property path/to/Class.php \
  --name=nombrePropiedad \
  --value="nuevo valor" \
  --type=string

# Añadir un elemento a un array
./codemod.phar class:add-to-array path/to/Class.php \
  --property=nombreArray \
  --key=clave \
  --value="valor" \
  --string

# Añadir un método desde un stub
./codemod.phar class:add-method path/to/Class.php \
  --stub=path/to/method_stub.php

# Añadir un método directamente
./codemod.phar class:add-method path/to/Class.php \
  --method="public function nuevoMetodo() { return true; }"
```

## Requisitos 📋
- PHP 8.1+
- Composer
- Extensión PHAR

## Contribución 🤝
1. Clonar repositorio
2. `composer install`
3. Hacer cambios en `src/`
4. Probar con `composer test`
5. Rebuild PHAR: `composer run build`

## Notas de Desarrollo 📓
```bash
# Debuggear comandos
./codemod.phar -vvv list

# Inspeccionar PHAR
php codemod.phar --info

# Ejecutar pruebas
php vendor/bin/pest
```

> **Importante**: Siempre verificar backups (.bak) después de cada modificación

## Características Implementadas

- Añadir traits a clases existentes
- Añadir propiedades a clases existentes (con soporte para tipos de datos)
- Modificar propiedades existentes (con soporte para tipos de datos)
- Añadir elementos a arrays (con soporte para valores string)
- Añadir métodos a clases existentes desde archivos stub (con soporte para stubs sin etiqueta PHP)

## Uso

### Instalación

```bash
# Clonar el repositorio
git clone https://github.com/tu-usuario/codemod-tool.git
cd codemod-tool

# Instalar dependencias
composer install

# Construir el archivo PHAR
composer build
```

### Comandos Disponibles

#### Añadir un Trait a una Clase

```bash
php codemod.phar class:add-trait ruta/a/la/clase.php --trait=NombreDelTrait
```

#### Añadir una Propiedad a una Clase

```bash
php codemod.phar class:add-property ruta/a/la/clase.php --name=nombrePropiedad --value="valorPorDefecto" --visibility=public --type=string
```

#### Modificar una Propiedad Existente

```bash
php codemod.phar class:modify-property ruta/a/la/clase.php --name=nombrePropiedad --value="nuevoValor" --type=string
```

#### Añadir un Elemento a un Array

```bash
php codemod.phar class:add-to-array ruta/a/la/clase.php --property=nombreArray --key=clave --value="valor" --string
```

#### Añadir un Método a una Clase desde un Archivo Stub

```bash
php codemod.phar class:add-method ruta/a/la/clase.php --stub=ruta/al/archivo/stub.php
```

### Opciones Globales

#### Modo Dry-Run

Todos los comandos aceptan la opción `--dry-run` que muestra los cambios que se realizarían sin aplicarlos realmente:

```bash
php codemod.phar class:add-trait app/Models/User.php --trait=HasApiTokens --dry-run
```

### Ejemplos de Uso

#### Añadir un Trait

```bash
php codemod.phar class:add-trait app/Models/User.php --trait=HasApiTokens
```

#### Añadir una Propiedad con Tipo

```bash
php codemod.phar class:add-property app/Models/User.php --name=apiKey --value="null" --visibility=protected --type=string
```

#### Modificar una Propiedad

```bash
php codemod.phar class:modify-property app/Models/User.php --name=status --value="active" --type=string
```

#### Añadir un Elemento a un Array como String

```bash
php codemod.phar class:add-to-array app/Models/User.php --property=fillable --key=0 --value=123 --string
```

#### Añadir un Método desde un Stub sin Etiqueta PHP

Crea un archivo stub con el código del método (no es necesario incluir la etiqueta PHP):

```php
// method_stub.php
public function getFullName(): string
{
    return $this->firstName . ' ' . $this->lastName;
}
```

Luego, ejecuta el comando:

```bash
php codemod.phar class:add-method app/Models/User.php --stub=method_stub.php
```

## Pruebas

El proyecto utiliza PEST para las pruebas. Para ejecutar las pruebas:

```bash
php vendor/bin/pest
```

Las pruebas cubren todos los comandos y sus diferentes opciones, asegurando que cada funcionalidad trabaje como se espera.

## Contribuir

Las contribuciones son bienvenidas. Por favor, abre un issue o un pull request para sugerir cambios o mejoras.

## Licencia

Este proyecto está licenciado bajo la Licencia MIT.