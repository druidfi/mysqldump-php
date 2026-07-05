<?php

namespace Druidfi\Mysqldump\Compress;

use Druidfi\Mysqldump\Exception\ConfigurationException;
use Druidfi\Mysqldump\Exception\DumpException;

class CompressBzip2 implements CompressInterface
{
    /** @var resource|false */
    private $fileHandler;

    /**
     * @throws ConfigurationException
     */
    public function __construct()
    {
        if (!function_exists('bzopen')) {
            throw new ConfigurationException('Compression is enabled, but bzip2 lib is not installed or configured properly');
        }
    }

    /**
     * @throws DumpException
     */
    public function open(string $filename): bool
    {
        $this->fileHandler = bzopen($filename, 'w');

        if (false === $this->fileHandler) {
            throw new DumpException('Output file is not writable');
        }

        return true;
    }

    /**
     * @throws DumpException
     */
    public function write(string $str): int
    {
        $bytesWritten = bzwrite($this->fileHandler, $str);

        if (false === $bytesWritten) {
            throw new DumpException('Writing to file failed! Probably, there is no more free space left?');
        }

        return $bytesWritten;
    }

    public function close(): bool
    {
        return bzclose($this->fileHandler);
    }
}
