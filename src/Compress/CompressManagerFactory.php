<?php

namespace Druidfi\Mysqldump\Compress;

use Exception;

abstract class CompressManagerFactory
{
    // List of available compression methods as constants.
    const GZIP  = 'Gzip';
    const BZIP2 = 'Bzip2';
    const NONE  = 'None';
    const GZIPSTREAM = 'Gzipstream';
    const ZSTD  = 'Zstd';
    const LZ4   = 'Lz4';

    public static array $methods = [
        self::NONE,
        self::GZIP,
        self::BZIP2,
        self::GZIPSTREAM,
        self::ZSTD,
        self::LZ4,
    ];

    /**
     * @throws Exception
     */
    public static function create(string $method, int $level = 0): CompressInterface
    {
        $method = ucfirst(strtolower($method));

        if (!in_array($method, self::$methods)) {
            throw new Exception("Compression method ($method) is not defined yet");
        }

        $methodClass = __NAMESPACE__."\\"."Compress".$method;

        // Pass compression level to the constructor if the method supports it
        if ($method === self::ZSTD && $level > 0) {
            return new $methodClass($level);
        } elseif ($method === self::LZ4 && $level > 0) {
            return new $methodClass($level);
        }

        return new $methodClass;
    }
}
