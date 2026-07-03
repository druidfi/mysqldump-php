<?php

declare(strict_types=1);

namespace Druidfi\Mysqldump\Exception;

use Exception;

/**
 * Base exception for all errors thrown by mysqldump-php.
 *
 * Extends the native Exception so existing catch (Exception) blocks
 * keep working; catch this class to handle any library error.
 */
class MysqldumpException extends Exception
{
}
