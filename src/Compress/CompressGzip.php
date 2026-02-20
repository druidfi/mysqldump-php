<?php

namespace Druidfi\Mysqldump\Compress;

use Exception;

class CompressGzip implements CompressInterface
{
    private $fileHandler;
    private int $level;

    /**
     * @throws Exception
     */
    public function __construct(int $level = 0)
    {
        if (!function_exists('gzopen')) {
            throw new Exception('Compression is enabled, but gzip lib is not installed or configured properly');
        }

        // gzip level: 0 = default, 1-9 = fast to best
        $this->level = ($level >= 1 && $level <= 9) ? $level : 0;
    }

    /**
     * @throws Exception
     */
    public function open(string $filename): bool
    {
        $mode = $this->level > 0 ? 'wb' . $this->level : 'wb';
        $this->fileHandler = gzopen($filename, $mode);

        if (false === $this->fileHandler) {
            throw new Exception('Output file is not writable');
        }

        return true;
    }

    /**
     * @throws Exception
     */
    public function write(string $str): int
    {
        $bytesWritten = gzwrite($this->fileHandler, $str);

        if (false === $bytesWritten) {
            throw new Exception('Writing to file failed! Probably, there is no more free space left?');
        }

        return $bytesWritten;
    }

    public function close(): bool
    {
        return gzclose($this->fileHandler);
    }
}
