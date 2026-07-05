<?php

declare(strict_types=1);

namespace Druidfi\Mysqldump\Exception;

/**
 * Thrown when dump settings are invalid: unknown options, values that
 * fail constraint validation, conflicting options, include-tables that
 * do not exist, or compression methods whose extension is not installed.
 */
class ConfigurationException extends MysqldumpException
{
}
