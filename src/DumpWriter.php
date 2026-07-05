<?php
declare(strict_types=1);

namespace Druidfi\Mysqldump;

use Druidfi\Mysqldump\Compress\CompressInterface;
use Druidfi\Mysqldump\Compress\CompressManagerFactory;
use Druidfi\Mysqldump\Exception\ConfigurationException;
use Druidfi\Mysqldump\Exception\DumpException;

/**
 * Class DumpWriter
 * 
 * Handles file output operations for mysqldump-php.
 */
class DumpWriter
{
    private CompressInterface $io;

    /**
     * Constructor of DumpWriter.
     *
     * @param DumpSettings $settings Settings for the dump
     */
    public function __construct(private readonly DumpSettings $settings)
    {
    }

    /**
     * Initialize the writer with the specified destination.
     *
     * @param string $destination Path to the output file or php://stdout
     * @throws ConfigurationException when the compression method is unavailable
     * @throws DumpException when the destination cannot be opened
     */
    public function initialize(string $destination): void
    {
        // Create a new compressManager to manage compressed output
        $this->io = CompressManagerFactory::create(
            $this->settings->getCompressMethod(),
            $this->settings->getCompressLevel()
        );

        // Create output file
        $this->io->open($destination);
    }

    /**
     * Write data to the output file.
     *
     * @param string $data Data to write
     * @return int Number of bytes written
     * @throws DumpException
     */
    public function write(string $data): int
    {
        return $this->io->write($data);
    }

    public function close(): bool
    {
        return $this->io->close();
    }
}