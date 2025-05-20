<?php

namespace Druidfi\Mysqldump\Compress;

use Exception;

class CompressZstd implements CompressInterface
{
    private $fileHandler;
    private int $compressionLevel;

    /**
     * @throws Exception
     */
    public function __construct(int $compressionLevel = 3)
    {
        if (!extension_loaded('zstd')) {
            throw new Exception('Compression is enabled, but zstd extension is not installed or configured properly');
        }
        
        // Ensure compression level is within valid range (1-22 for zstd)
        $this->compressionLevel = max(1, min(22, $compressionLevel));
    }

    /**
     * @throws Exception
     */
    public function open(string $filename): bool
    {
        $this->fileHandler = fopen($filename, 'wb');

        if (false === $this->fileHandler) {
            throw new Exception('Output file is not writable');
        }

        // Create a zstd compression context with the specified compression level
        $this->fileHandler = zstd_compress_stream_begin($this->fileHandler, $this->compressionLevel);

        return true;
    }

    /**
     * @throws Exception
     */
    public function write(string $str): int
    {
        $bytesWritten = zstd_compress_stream_update($this->fileHandler, $str);

        if (false === $bytesWritten) {
            throw new Exception('Writing to file failed! Probably, there is no more free space left?');
        }

        return $bytesWritten;
    }

    public function close(): bool
    {
        $result = zstd_compress_stream_end($this->fileHandler);
        return $result !== false;
    }
}