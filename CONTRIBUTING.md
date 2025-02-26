# Guía de Contribución para CodeMod Tool

## Arquitectura del Proyecto

### Visión General

CodeMod Tool es una herramienta de línea de comandos diseñada para modificar automáticamente código PHP mediante el análisis y manipulación del AST (Abstract Syntax Tree). La herramienta está construida con PHP y se distribuye como un archivo PHAR ejecutable.

### Estructura de Directorios

```
codemod-tool/
├── bin/                  # Scripts ejecutables
│   └── codemod           # Punto de entrada principal (PHP)
├── src/                  # Código fuente
│   ├── Commands/         # Comandos de Symfony Console
│   ├── Modifiers/        # Modificadores de código
│   ├── Parser/           # Analizadores de código
│   ├── CLI.php           # Clase principal de la aplicación
│   └── FileHandler.php   # Manejo de archivos
├── tests/                # Pruebas y ejemplos
├── vendor/               # Dependencias (gestionadas por Composer)
├── box.json              # Configuración para construir el PHAR
├── composer.json         # Dependencias y scripts
├── codemod.phar          # Archivo PHAR ejecutable (generado)
└── stub.php              # Punto de entrada del PHAR
```

## Flujo de Ejecución

### 1. Punto de Entrada: `bin/codemod`

Este archivo es el punto de entrada principal cuando se ejecuta el proyecto directamente desde el código fuente. Su contenido es simple:

```php
#!/usr/bin/env php
<?php

require __DIR__ . '/../vendor/autoload.php';

use CodeModTool\CLI;

// Iniciar la aplicación CLI
$app = new CLI();
$app->run();
```

Este script:
1. Carga el autoloader de Composer
2. Instancia la clase `CLI`
3. Ejecuta el método `run()`

### 2. Clase Principal: `src/CLI.php`

Esta clase es el núcleo de la aplicación. Configura la aplicación de Symfony Console y registra los comandos disponibles:

```php
<?php

namespace CodeModTool;

use Symfony\Component\Console\Application;
use CodeModTool\Commands\ModifyEnumCommand;
use CodeModTool\Parser\CodeParser;
use CodeModTool\Modifiers\EnumModifier;

class CLI
{
    public function run(): int
    {
        $application = new Application('CodeMod Tool', '1.0.0');
        $application->add(new ModifyEnumCommand(
            new CodeParser(),
            new FileHandler(),
            new EnumModifier()
        ));
        return $application->run();
    }
}
```

### 3. Comandos: `src/Commands/`

Los comandos implementan la interfaz de línea de comandos utilizando Symfony Console. Por ejemplo, `ModifyEnumCommand.php` maneja el comando `enum:modify`:

```php
class ModifyEnumCommand extends Command
{
    // ...
    
    protected function configure()
    {
        $this->setName('enum:modify')
            ->setDescription('Add a case to an enum')
            ->addArgument('file', InputArgument::REQUIRED, 'Path to enum file')
            ->addOption('case', null, InputOption::VALUE_REQUIRED, 'Case name')
            ->addOption('value', null, InputOption::VALUE_REQUIRED, 'Case value');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Implementación del comando
    }
}
```

### 4. Componentes Principales

- **Parser (`src/Parser/CodeParser.php`)**: Utiliza `nikic/php-parser` para analizar el código PHP y convertirlo en un AST.
- **Modifiers (`src/Modifiers/`)**: Contiene clases que modifican el AST para realizar cambios específicos en el código.
- **FileHandler (`src/FileHandler.php`)**: Maneja la lectura y escritura de archivos, incluyendo la creación de copias de seguridad.

## Empaquetado con Box (PHAR)

### ¿Qué es Box?

[Box](https://github.com/box-project/box) es una herramienta que permite empaquetar aplicaciones PHP en un único archivo PHAR ejecutable. PHAR (PHP Archive) es similar a un archivo JAR en Java, permitiendo distribuir una aplicación completa como un único archivo.

### Proceso de Construcción del PHAR

1. **Configuración**: El archivo `box.json` define cómo se construirá el PHAR:

```json
{
    "output": "codemod.phar",
    "compression": "GZ",
    "directories": ["src", "vendor"],
    "stub": "stub.php",
    "force-autodiscovery": true
}
```

2. **Stub**: El archivo `stub.php` es el punto de entrada del PHAR:

```php
#!/usr/bin/env php
<?php
Phar::mapPhar('codemod.phar');
require 'phar://codemod.phar/bin/codemod';
__HALT_COMPILER(); 
```

Este stub:
   - Mapea el PHAR a un nombre específico
   - Carga el script principal (`bin/codemod`)
   - Finaliza la compilación con `__HALT_COMPILER()`

3. **Construcción**: El comando `composer run build` ejecuta:
   - `composer install --no-dev`: Instala dependencias sin las de desarrollo
   - `./box.phar compile`: Construye el PHAR según la configuración

### Relación entre `codemod.phar` y `bin/codemod`

- `bin/codemod` es el punto de entrada cuando se ejecuta desde el código fuente
- Cuando se construye el PHAR, `bin/codemod` se incluye dentro del archivo PHAR
- `stub.php` actúa como puente, cargando `bin/codemod` desde dentro del PHAR
- El resultado final (`codemod.phar`) es un archivo ejecutable independiente que contiene toda la aplicación

## Flujo de Datos

1. El usuario ejecuta `codemod.phar enum:modify archivo.php --case=NUEVO --value=valor`
2. El stub carga `bin/codemod` desde dentro del PHAR
3. `bin/codemod` instancia `CLI` y ejecuta `run()`
4. `CLI` configura la aplicación Symfony Console y registra los comandos
5. Symfony Console analiza los argumentos y opciones, y ejecuta `ModifyEnumCommand`
6. `ModifyEnumCommand` utiliza:
   - `CodeParser` para analizar el código PHP
   - `EnumModifier` para modificar el AST
   - `FileHandler` para leer/escribir archivos
7. El código modificado se guarda en el archivo original, con una copia de seguridad

## Cómo Contribuir

### Configuración del Entorno de Desarrollo

1. Clonar el repositorio:
   ```bash
   git clone [url-del-repositorio]
   cd codemod-tool
   ```

2. Instalar dependencias:
   ```bash
   composer install
   ```

3. Instalar Box (si no está instalado):
   ```bash
   bash install-box.sh
   ```

### Flujo de Trabajo para Contribuciones

1. **Crear una rama**: `git checkout -b feature/nueva-funcionalidad`
2. **Implementar cambios**: Añadir/modificar código en `src/`
3. **Probar localmente**:
   ```bash
   php bin/codemod [comando] [opciones]
   ```
4. **Construir el PHAR**:
   ```bash
   composer run build
   ```
5. **Probar el PHAR**:
   ```bash
   php codemod.phar [comando] [opciones]
   ```
6. **Enviar Pull Request**: Con una descripción clara de los cambios

### Añadir Nuevos Comandos

1. Crear una nueva clase en `src/Commands/` que extienda `Symfony\Component\Console\Command\Command`
2. Implementar los métodos `configure()` y `execute()`
3. Registrar el comando en `src/CLI.php`

### Añadir Nuevos Modificadores

1. Crear una nueva clase en `src/Modifiers/`
2. Implementar métodos para modificar el AST
3. Crear un comando correspondiente o actualizar uno existente para utilizar el nuevo modificador

## Consejos para Depuración

- Usar la opción `-vvv` para obtener información detallada:
  ```bash
  php codemod.phar -vvv enum:modify archivo.php --case=NUEVO --value=valor
  ```

- Inspeccionar el contenido del PHAR:
  ```bash
  php -r "print_r(new Phar('codemod.phar'));"
  ```

- Verificar las copias de seguridad (`.bak`) después de cada modificación

## Convenciones de Código

- Seguir PSR-12 para el estilo de código
- Documentar todas las clases y métodos con DocBlocks
- Mantener una cobertura de pruebas adecuada
- Utilizar tipos de retorno y declaraciones de tipo cuando sea posible

## Recursos Adicionales

- [Documentación de PHP-Parser](https://github.com/nikic/PHP-Parser/blob/master/doc/0_Introduction.markdown)
- [Documentación de Symfony Console](https://symfony.com/doc/current/components/console.html)
- [Documentación de Box](https://github.com/box-project/box/blob/main/doc/index.md) 