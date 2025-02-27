# Tareas Pendientes para CodeMod

## Mejoras para Modificación de Enums

- [x] Soporte para añadir múltiples casos a enums en una sola operación
- [x] Comando `enum:batch-modify` para simplificar la modificación de enums
- [ ] Plantillas predefinidas para casos comunes de enums (estados, niveles, etc.)
- [ ] Generación automática de casos basados en patrones
- [ ] Funcionalidades avanzadas para enums:
  - [ ] Renombrar casos existentes
  - [ ] Reordenar casos
  - [ ] Eliminar casos

## Mejoras para Modificación de Clases

- [x] Añadir traits a clases existentes
- [x] Añadir propiedades a clases existentes
- [x] Modificar propiedades existentes
- [x] Añadir elementos a arrays
- [x] Añadir métodos a clases existentes
- [x] Comando batch para añadir múltiples traits en una sola operación
- [x] Comando batch para añadir múltiples propiedades en una sola operación
- [x] Comando batch para añadir múltiples métodos en una sola operación
- [ ] Comando para eliminar traits
- [ ] Comando para eliminar propiedades
- [ ] Comando para eliminar métodos
- [ ] Soporte para renombrar propiedades
- [ ] Soporte para modificar visibilidad de propiedades
- [ ] Soporte para modificar tipos de datos de propiedades
- [ ] Soporte para modificar métodos existentes

## Mejoras Generales

- [x] Modo dry-run (ya implementado)
  - [x] Mostrar cambios sin aplicarlos
  - [x] Mostrar diferencias simples de código
  - [ ] Mejorar visualización de diferencias (más contexto, colores más precisos)
  - [ ] Opción para generar archivo de diferencias
  - [ ] Visualización interactiva de cambios
  - [ ] Estadísticas de cambios

- [ ] Formateo de código
  - [ ] Integración con una herramienta de formateo (php-cs-fixer, prettier)
  - [ ] Opción para mantener el estilo original

- [ ] Sistema de plugins
  - [ ] Arquitectura para plugins
  - [ ] Sistema de descubrimiento de plugins
  - [ ] Documentación para crear plugins

## Mejoras de Experiencia de Usuario

- [ ] Interfaz interactiva
  - [ ] Autocompletado de comandos
  - [ ] Sugerencias de comandos
  - [ ] Vista previa de cambios

- [ ] Documentación avanzada
  - [ ] Ejemplos completos para cada comando
  - [ ] Tutoriales para casos de uso comunes
  - [ ] Guía para desarrolladores

## Funcionalidades Adicionales

- [ ] Soporte para modificar interfaces
- [ ] Soporte para modificar traits
- [ ] Soporte para modificar archivos de configuración (arrays)
- [ ] Soporte para migraciones entre versiones de PHP
- [ ] Análisis de impacto de cambios

## Nuevos Comandos Potenciales

- [ ] `namespace:move` - Mover clases a diferentes namespaces
- [ ] `class:extract-interface` - Extraer una interfaz de una clase
- [ ] `class:extract-trait` - Extraer un trait de una clase
- [ ] `class:implement-interface` - Implementar una interfaz en una clase
- [ ] `method:extract` - Extraer un método a partir de un bloque de código 