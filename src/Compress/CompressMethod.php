<?php

declare(strict_types=1);

namespace Druidfi\Mysqldump\Compress;

use Druidfi\Mysqldump\Exception\ConfigurationException;

/**
 * Available compression methods for dump output.
 */
enum CompressMethod: string
{
    case None = 'None';
    case Gzip = 'Gzip';
    case Bzip2 = 'Bzip2';
    case Gzipstream = 'Gzipstream';
    case Zstd = 'Zstd';
    case Lz4 = 'Lz4';

    /**
     * Resolve a compression method from user input, case-insensitively.
     *
     * @throws ConfigurationException when the method is not defined
     */
    public static function fromName(string $method): self
    {
        $normalized = ucfirst(strtolower($method));

        return self::tryFrom($normalized)
            ?? throw new ConfigurationException("Compression method ($normalized) is not defined yet");
    }

    /**
     * Highest supported compression level, or null when the method
     * does not take a level.
     */
    public function maxLevel(): ?int
    {
        return match ($this) {
            self::Gzip => 9,
            self::Lz4 => 12,
            self::Zstd => 22,
            default => null,
        };
    }

    /**
     * Create the compressor for this method. A level of 0 means the
     * compressor's own default level (Zstd defaults to 3, Lz4 to 1).
     *
     * @throws ConfigurationException when a required extension is missing
     */
    public function compressor(int $level = 0): CompressInterface
    {
        return match ($this) {
            self::None => new CompressNone(),
            self::Bzip2 => new CompressBzip2(),
            self::Gzipstream => new CompressGzipstream(),
            self::Gzip => $level > 0 ? new CompressGzip($level) : new CompressGzip(),
            self::Zstd => $level > 0 ? new CompressZstd($level) : new CompressZstd(),
            self::Lz4 => $level > 0 ? new CompressLz4($level) : new CompressLz4(),
        };
    }
}
