<?php

declare(strict_types=1);

namespace Druidfi\Mysqldump\Compress;

use Druidfi\Mysqldump\Exception\ConfigurationException;

abstract class CompressManagerFactory
{
    // List of available compression methods as constants.
    public const string GZIP = 'Gzip';
    public const string BZIP2 = 'Bzip2';
    public const string NONE = 'None';
    public const string GZIPSTREAM = 'Gzipstream';
    public const string ZSTD = 'Zstd';
    public const string LZ4 = 'Lz4';

    /**
     * @throws ConfigurationException
     */
    public static function create(string $method, int $level = 0): CompressInterface
    {
        return CompressMethod::fromName($method)->compressor($level);
    }
}
