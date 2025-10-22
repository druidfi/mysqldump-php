# PHP Attributes Usage Guide

This document describes how native PHP 8.0+ Attributes are utilized in the mysqldump-php project to provide metadata, validation rules, and other useful information about code elements.

## Overview

PHP Attributes provide a structured way to add metadata to declarations of classes, methods, properties, parameters, and constants. This project uses attributes for:

1. **Configuration metadata** - Document default values and descriptions
2. **Validation rules** - Define constraints on settings and parameters
3. **Deprecation marking** - Mark deprecated methods and settings
4. **Dependency injection** - Metadata for DI containers

## Available Attributes

### 1. DefaultValue

Marks a configuration setting with its default value and optional description.

**Namespace:** `Druidfi\Mysqldump\Attribute\DefaultValue`

**Targets:** Class constants, Properties

**Parameters:**
- `mixed $value` - The default value
- `?string $description` - Optional description of the setting

**Example:**
```php
use Druidfi\Mysqldump\Attribute\DefaultValue;

class MyConfig
{
    #[DefaultValue(value: true, description: 'Enable compression')]
    public const COMPRESS_ENABLED = 'compress-enabled';
    
    #[DefaultValue(value: 1000000, description: 'Buffer size in bytes')]
    public const BUFFER_SIZE = 'buffer-size';
}
```

### 2. Constraint

Defines validation constraints for configuration settings or parameters.

**Namespace:** `Druidfi\Mysqldump\Attribute\Constraint`

**Targets:** Properties, Parameters, Class constants

**Parameters:**
- `?array $allowedValues` - List of allowed values
- `?int $min` - Minimum value (for numeric types)
- `?int $max` - Maximum value (for numeric types)
- `?string $pattern` - Regular expression pattern
- `?string $message` - Custom validation error message

**Example:**
```php
use Druidfi\Mysqldump\Attribute\Constraint;

class ValidationExample
{
    #[Constraint(min: 0, max: 9, message: 'Value must be between 0 and 9')]
    public const COMPRESSION_LEVEL = 'compression-level';
    
    #[Constraint(
        allowedValues: ['None', 'Gzip', 'Bzip2', 'Zstandard'], 
        message: 'Invalid compression method'
    )]
    public const COMPRESSION_METHOD = 'compression-method';
    
    #[Constraint(pattern: '/^[a-z_]+$/', message: 'Must be lowercase with underscores')]
    public const TABLE_PREFIX = 'table-prefix';
}
```

### 3. Deprecated

Marks a class, method, property, or constant as deprecated with information about alternatives.

**Namespace:** `Druidfi\Mysqldump\Attribute\Deprecated`

**Targets:** Classes, Methods, Properties, Class constants

**Parameters:**
- `string $reason` - Why the element is deprecated
- `?string $alternative` - Suggested alternative
- `?string $since` - Version when it was deprecated
- `?string $removeIn` - Version when it will be removed

**Example:**
```php
use Druidfi\Mysqldump\Attribute\Deprecated;

class DeprecationExample
{
    #[Deprecated(
        reason: 'This option is no longer maintained',
        alternative: 'Use new_option instead',
        since: '2.0',
        removeIn: '3.0'
    )]
    public const OLD_OPTION = 'old-option';
    
    #[Deprecated(
        reason: 'Replaced by more efficient implementation',
        alternative: 'Use processDataV2()',
        since: '2.5'
    )]
    public function processData(): void
    {
        // old implementation
    }
}
```

### 4. ValidatesValue

Marks a parameter or property for runtime validation.

**Namespace:** `Druidfi\Mysqldump\Attribute\ValidatesValue`

**Targets:** Parameters, Properties

**Parameters:**
- `?string $type` - Expected type name
- `bool $notNull` - Whether null values are forbidden
- `bool $notEmpty` - Whether empty values are forbidden
- `?array $allowedValues` - List of allowed values

**Example:**
```php
use Druidfi\Mysqldump\Attribute\ValidatesValue;

class ValidationExample
{
    public function setCompression(
        #[ValidatesValue(
            allowedValues: ['None', 'Gzip', 'Bzip2'],
            notNull: true,
            notEmpty: true
        )]
        string $method
    ): void {
        // Implementation
    }
    
    public function setTables(
        #[ValidatesValue(type: 'array', notEmpty: true)]
        array $tables
    ): void {
        // Implementation
    }
}
```

### 5. Injectable

Marks a class, property, or parameter as injectable for dependency injection.

**Namespace:** `Druidfi\Mysqldump\Attribute\Injectable`

**Targets:** Classes, Properties, Parameters

**Parameters:**
- `?string $serviceId` - Service identifier in the DI container
- `bool $required` - Whether the dependency is required
- `?string $factory` - Factory method to create the service

**Example:**
```php
use Druidfi\Mysqldump\Attribute\Injectable;

#[Injectable(serviceId: 'mysqldump.dumper')]
class Mysqldump
{
    public function __construct(
        #[Injectable(serviceId: 'database.connector', required: true)]
        private DatabaseConnector $connector,
        
        #[Injectable(serviceId: 'dump.writer', factory: 'createWriter')]
        private DumpWriter $writer
    ) {
    }
}
```

## Practical Usage in mysqldump-php

### Configuration Options with Attributes

The `ConfigOption` class demonstrates comprehensive attribute usage for all configuration settings:

```php
use Druidfi\Mysqldump\Attribute\DefaultValue;
use Druidfi\Mysqldump\Attribute\Constraint;
use Druidfi\Mysqldump\Attribute\Deprecated;

class ConfigOption
{
    #[DefaultValue(value: 'None', description: 'Compression method to use')]
    #[Constraint(
        allowedValues: ['None', 'Gzip', 'Bzip2', 'Zstandard', 'LZ4'],
        message: 'Must be a valid compression method'
    )]
    public const COMPRESS = 'compress';
    
    #[DefaultValue(value: 0, description: 'Compression level (0-9)')]
    #[Constraint(min: 0, max: 9, message: 'Compression level must be between 0 and 9')]
    public const COMPRESS_LEVEL = 'compress-level';
    
    #[DefaultValue(value: true, description: 'Disable foreign key checks (deprecated)')]
    #[Deprecated(
        reason: 'This option is deprecated and may be removed in a future version',
        alternative: 'Use init_commands to set FOREIGN_KEY_CHECKS manually',
        since: '2.0'
    )]
    public const DISABLE_FOREIGN_KEYS_CHECK = 'disable-foreign-keys-check';
}
```

### Reading Attributes at Runtime

Attributes can be read using PHP's Reflection API:

```php
use ReflectionClass;
use Druidfi\Mysqldump\Attribute\DefaultValue;
use Druidfi\Mysqldump\Attribute\Deprecated;

function getConfigMetadata(string $constant): array
{
    $reflection = new ReflectionClass(ConfigOption::class);
    $constant = $reflection->getReflectionConstant($constant);
    
    $metadata = [];
    
    // Get default value
    $defaultAttrs = $constant->getAttributes(DefaultValue::class);
    if (!empty($defaultAttrs)) {
        $attr = $defaultAttrs[0]->newInstance();
        $metadata['default'] = $attr->value;
        $metadata['description'] = $attr->description;
    }
    
    // Check if deprecated
    $deprecatedAttrs = $constant->getAttributes(Deprecated::class);
    if (!empty($deprecatedAttrs)) {
        $attr = $deprecatedAttrs[0]->newInstance();
        $metadata['deprecated'] = true;
        $metadata['reason'] = $attr->reason;
        $metadata['alternative'] = $attr->alternative;
    }
    
    return $metadata;
}

// Usage
$metadata = getConfigMetadata('COMPRESS');
// Returns: ['default' => 'None', 'description' => 'Compression method to use']
```

### Validation Using Constraint Attributes

```php
use ReflectionClass;
use Druidfi\Mysqldump\Attribute\Constraint;

function validateConfigValue(string $constant, mixed $value): bool
{
    $reflection = new ReflectionClass(ConfigOption::class);
    $constant = $reflection->getReflectionConstant($constant);
    
    $constraintAttrs = $constant->getAttributes(Constraint::class);
    if (empty($constraintAttrs)) {
        return true; // No constraints
    }
    
    $constraint = $constraintAttrs[0]->newInstance();
    
    // Check allowed values
    if ($constraint->allowedValues !== null) {
        if (!in_array($value, $constraint->allowedValues, true)) {
            throw new InvalidArgumentException(
                $constraint->message ?? 'Invalid value'
            );
        }
    }
    
    // Check min/max for numeric values
    if (is_numeric($value)) {
        if ($constraint->min !== null && $value < $constraint->min) {
            throw new InvalidArgumentException(
                $constraint->message ?? "Value must be at least {$constraint->min}"
            );
        }
        if ($constraint->max !== null && $value > $constraint->max) {
            throw new InvalidArgumentException(
                $constraint->message ?? "Value must be at most {$constraint->max}"
            );
        }
    }
    
    return true;
}
```

## Benefits of Using Attributes

1. **Self-documenting code** - Metadata lives with the code it describes
2. **Type-safe metadata** - Attributes are classes with proper type checking
3. **Runtime access** - Can be read using Reflection API for dynamic behavior
4. **IDE support** - Modern IDEs understand attributes and provide autocomplete
5. **Standardization** - Consistent way to express metadata across the codebase
6. **Extensibility** - Easy to add new attribute types as needed

## Best Practices

1. **Keep attributes focused** - Each attribute should have a single, clear purpose
2. **Use readonly properties** - Attribute data should be immutable
3. **Provide good defaults** - Make optional parameters truly optional
4. **Document attribute usage** - Include docblocks explaining the attribute's purpose
5. **Combine attributes** - Multiple attributes can be applied to the same element
6. **Validate at appropriate times** - Use attributes for metadata, not as the sole validation mechanism

## Further Reading

- [PHP Attributes RFC](https://wiki.php.net/rfc/attributes_v2)
- [PHP Manual: Attributes](https://www.php.net/manual/en/language.attributes.php)
- [Reflection API](https://www.php.net/manual/en/book.reflection.php)
