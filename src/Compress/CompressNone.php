<?php

namespace Druidfi\Mysqldump\Compress;

use Druidfi\Mysqldump\Exception\DumpException;

class CompressNone implements CompressInterface
{
    /** @var resource|false */
    private $fileHandler;

    /**
     * @throws DumpException
     */
    public function open(string $filename): bool
    {
        $this->fileHandler = fopen($filename, 'wb');

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
        $bytesWritten = fwrite($this->fileHandler, $str);

        if (false === $bytesWritten) {
            throw new DumpException('Writing to file failed! Probably, there is no more free space left?');
        }

        return $bytesWritten;
    }

    public function close(): bool
    {
        return fclose($this->fileHandler);
    }
}
