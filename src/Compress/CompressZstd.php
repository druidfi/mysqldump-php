<?php

namespace Druidfi\Mysqldump\Compress;

use Druidfi\Mysqldump\Exception\ConfigurationException;
use Druidfi\Mysqldump\Exception\DumpException;

class CompressZstd implements CompressInterface
{
    /** @var resource|false */
    private $fileHandler;
    private readonly int $compressionLevel;

    /**
     * @throws ConfigurationException
     */
    public function __construct(int $compressionLevel = 3)
    {
        if (!extension_loaded('zstd')) {
            throw new ConfigurationException('Compression is enabled, but zstd extension is not installed or configured properly');
        }
        
        // Ensure compression level is within valid range (1-22 for zstd)
        $this->compressionLevel = max(1, min(22, $compressionLevel));
    }

    /**
     * @throws DumpException
     */
    public function open(string $filename): bool
    {
        $this->fileHandler = fopen($filename, 'wb');

        if (false === $this->fileHandler) {
            throw new DumpException('Output file is not writable');
        }

        // Create a zstd compression context with the specified compression level
        $this->fileHandler = zstd_compress_stream_begin($this->fileHandler, $this->compressionLevel);

        return true;
    }

    /**
     * @throws DumpException
     */
    public function write(string $str): int
    {
        $bytesWritten = zstd_compress_stream_update($this->fileHandler, $str);

        if (false === $bytesWritten) {
            throw new DumpException('Writing to file failed! Probably, there is no more free space left?');
        }

        return $bytesWritten;
    }

    public function close(): bool
    {
        $result = zstd_compress_stream_end($this->fileHandler);
        return $result !== false;
    }
}