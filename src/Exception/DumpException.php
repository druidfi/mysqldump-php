<?php

declare(strict_types=1);

namespace Druidfi\Mysqldump\Exception;

/**
 * Thrown when the dump itself fails: output not writable, write
 * errors (e.g. disk full), or unexpected results from the server
 * while reading database object structures.
 */
class DumpException extends MysqldumpException
{
}
