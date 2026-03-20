# Architecture: oxideshop-unified-namespace-generator

## Purpose

Generates PHP class files in the `OxidEsales\Eshop` unified namespace that forward to the actual edition-specific classes (Community, Professional, Enterprise). This allows shop modules to write code against `OxidEsales\Eshop\...` without knowing which OXID edition is installed.

## Directory Structure

```
src/
  Generator.php                                — Writes forwarding class files to the output directory
  Plugin.php                                   — Composer plugin entry point (runs generator on install/update)
  Unified_Name_Space_Class_Map_Provider.php    — Provides the mapping: unified class → edition class
  Edition_Class_Map_Loader.php                 — Loads the edition's class map YAML/array
  Backwards_Compatibility_Class_Map_Provider.php — Provides mappings for deprecated class names
  Exceptions/
    File_System_Compatibility_Exception.php
    Invalid_Unified_Namespace_Class_Map_Exception.php
    Output_Directory_Validation_Exception.php
    Permission_Exception.php
```

## Key Design Decisions

- **Composer plugin**: `Plugin` implements `Composer\Plugin\PluginInterface` so generation runs automatically during `composer install` / `composer update`
- **Forwarding classes**: Each generated file contains a class that `extends` or is aliased to the real edition class — not a proxy with dynamic dispatch; the PHP engine resolves the class statically
- **Map-driven**: Class maps are declarative arrays; switching edition is achieved by changing which map is loaded

## Extension Points

- Provide a custom `Unified_Name_Space_Class_Map_Provider` implementation to add project-specific class mappings
