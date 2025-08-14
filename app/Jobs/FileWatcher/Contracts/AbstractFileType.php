<?php

namespace App\Jobs\FileWatcher\Contracts;

use App\Console\Commands\FileWatcherCommand;
use App\Enums\FileChangesEnum;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

abstract class AbstractFileType implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected readonly FileChangesEnum $fileChangeType,
        protected readonly string $filePath,
    ) {
        // nth
    }

    abstract public function handle(): void;

    protected function cacheFile(?string $filePath = null): self
    {
        if (! $filePath) {
            $filePath = $this->filePath;
        }

        Cache::put(
            key: FileWatcherCommand::IGNORE_CACHE_KEY.$filePath,
            value: true,
        );

        return $this;
    }

    protected function isCreated(): bool
    {
        return $this->fileChangeType === FileChangesEnum::CREATED;
    }

    protected function isModified(): bool
    {
        return $this->fileChangeType === FileChangesEnum::MODIFIED;
    }
}
