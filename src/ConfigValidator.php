<?php
declare(strict_types=1);

namespace Druidfi\Mysqldump;

use Druidfi\Mysqldump\Attribute\Constraint;
use Druidfi\Mysqldump\Attribute\DefaultValue;
use Druidfi\Mysqldump\Attribute\Deprecated;
use Exception;
use ReflectionClass;
use ReflectionClassConstant;

/**
 * Validates configuration options using PHP Attributes from ConfigOption class.
 * Reads DefaultValue, Constraint, and Deprecated attributes via reflection.
 */
class ConfigValidator
{
    private static ?array $metadataCache = null;

    /**
     * Build metadata from ConfigOption attributes.
     * 
     * @return array<string, array{default: mixed, description: string|null, constraint: Constraint|null, deprecated: Deprecated|null}>
     */
    private static function buildMetadata(): array
    {
        if (self::$metadataCache !== null) {
            return self::$metadataCache;
        }

        $metadata = [];
        $reflection = new ReflectionClass(ConfigOption::class);
        
        foreach ($reflection->getReflectionConstants() as $constant) {
            $optionName = $constant->getValue();
            $metadata[$optionName] = self::extractConstantMetadata($constant);
        }

        self::$metadataCache = $metadata;
        return $metadata;
    }

    /**
     * Extract attribute metadata from a constant.
     */
    private static function extractConstantMetadata(ReflectionClassConstant $constant): array
    {
        $data = [
            'default' => null,
            'description' => null,
            'constraint' => null,
            'deprecated' => null,
        ];

        // Extract DefaultValue attribute
        $defaultAttrs = $constant->getAttributes(DefaultValue::class);
        if (!empty($defaultAttrs)) {
            $attr = $defaultAttrs[0]->newInstance();
            $data['default'] = $attr->value;
            $data['description'] = $attr->description;
        }

        // Extract Constraint attribute
        $constraintAttrs = $constant->getAttributes(Constraint::class);
        if (!empty($constraintAttrs)) {
            $data['constraint'] = $constraintAttrs[0]->newInstance();
        }

        // Extract Deprecated attribute
        $deprecatedAttrs = $constant->getAttributes(Deprecated::class);
        if (!empty($deprecatedAttrs)) {
            $data['deprecated'] = $deprecatedAttrs[0]->newInstance();
        }

        return $data;
    }

    /**
     * Get default values from ConfigOption attributes.
     * 
     * @return array<string, mixed>
     */
    public static function getDefaults(): array
    {
        $metadata = self::buildMetadata();
        $defaults = [];

        foreach ($metadata as $optionName => $data) {
            if ($data['default'] !== null) {
                $defaults[$optionName] = $data['default'];
            }
        }

        return $defaults;
    }

    /**
     * Validate a configuration option value against its Constraint attribute.
     * 
     * @throws Exception if validation fails
     */
    public static function validate(string $optionName, mixed $value): void
    {
        $metadata = self::buildMetadata();

        if (!isset($metadata[$optionName])) {
            // Option not in ConfigOption class, skip attribute validation
            return;
        }

        $constraint = $metadata[$optionName]['constraint'];
        if ($constraint === null) {
            return; // No constraint defined
        }

        // Validate allowed values
        if ($constraint->allowedValues !== null) {
            if (!in_array($value, $constraint->allowedValues, true)) {
                $allowed = implode(', ', $constraint->allowedValues);
                $message = $constraint->message ?? "Invalid value for '{$optionName}'. Allowed: {$allowed}";
                throw new Exception($message);
            }
        }

        // Validate numeric constraints
        if (is_numeric($value)) {
            if ($constraint->min !== null && $value < $constraint->min) {
                $message = $constraint->message ?? "Value for '{$optionName}' must be at least {$constraint->min}";
                throw new Exception($message);
            }

            if ($constraint->max !== null && $value > $constraint->max) {
                $message = $constraint->message ?? "Value for '{$optionName}' must be at most {$constraint->max}";
                throw new Exception($message);
            }
        }

        // Validate pattern
        if ($constraint->pattern !== null && is_string($value)) {
            if (!preg_match($constraint->pattern, $value)) {
                $message = $constraint->message ?? "Value for '{$optionName}' does not match required pattern";
                throw new Exception($message);
            }
        }
    }

    /**
     * Check if an option is deprecated and return deprecation info.
     * 
     * @return array{deprecated: bool, reason: string|null, alternative: string|null, since: string|null}|null
     */
    public static function checkDeprecated(string $optionName): ?array
    {
        $metadata = self::buildMetadata();

        if (!isset($metadata[$optionName])) {
            return null;
        }

        $deprecated = $metadata[$optionName]['deprecated'];
        if ($deprecated === null) {
            return null;
        }

        return [
            'deprecated' => true,
            'reason' => $deprecated->reason,
            'alternative' => $deprecated->alternative,
            'since' => $deprecated->since,
        ];
    }

    /**
     * Validate all settings in a configuration array.
     * 
     * @throws Exception if any validation fails
     */
    public static function validateAll(array $settings): void
    {
        foreach ($settings as $optionName => $value) {
            self::validate($optionName, $value);
        }
    }
}
