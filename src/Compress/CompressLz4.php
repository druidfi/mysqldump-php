<?php

namespace Druidfi\Mysqldump\Compress;

use Druidfi\Mysqldump\Exception\ConfigurationException;
use Druidfi\Mysqldump\Exception\DumpException;

class CompressLz4 implements CompressInterface
{
    /** @var resource|false */
    private $fileHandler;
    private readonly int $compressionLevel;

    /**
     * @throws ConfigurationException
     */
    public function __construct(int $compressionLevel = 1)
    {
        if (!extension_loaded('lz4')) {
            throw new ConfigurationException('Compression is enabled, but lz4 extension is not installed or configured properly');
        }
        
        // Ensure compression level is within valid range (1-12 for LZ4)
        $this->compressionLevel = max(1, min(12, $compressionLevel));
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

        // Create an LZ4 compression context
        $this->fileHandler = lz4_compress_open($this->fileHandler, $this->compressionLevel);

        if (false === $this->fileHandler) {
            throw new DumpException('Failed to initialize LZ4 compression');
        }

        return true;
    }

    /**
     * @throws DumpException
     */
    public function write(string $str): int
    {
        $bytesWritten = lz4_compress_write($this->fileHandler, $str);

        if (false === $bytesWritten) {
            throw new DumpException('Writing to file failed! Probably, there is no more free space left?');
        }

        return $bytesWritten;
    }

    public function close(): bool
    {
        return lz4_compress_close($this->fileHandler);
    }
}