<?php

declare(strict_types=1);

namespace BalatD\KernUx\Asset;

final readonly class InstallResult
{
    public function __construct(
        public string $version,
        public string $targetPath,
        public int $fileCount,
        public bool $skipped = false,
    ) {}
}
