<?php

namespace App\Console\Commands;

use App\Enums\FileChangesEnum;
use App\Jobs\FileWatcher\ProcessFileChanges;
use FilesystemIterator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use React\EventLoop\Loop;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use UnexpectedValueException;

class FileWatcherCommand extends Command
{
    public const string IGNORE_CACHE_KEY = 'file_watcher_ignore_list';

    protected $signature = 'app:file-watcher
        {path=./storage/app/private : The path to watch for file changes.}
        {--interval=1.0 : The interval in seconds to check for changes.}
    ';

    protected $description = 'Watch a directory for file changes and log events ';

    private int $newDirPermissions = 0755;

    private string $path = '';

    private array $fileTimestamps = [];

    private array $ignoreList = [];

    private string $logPrefix = 'FileWatcher // ';

    public function handle(): int
    {
        $this->path = (string) $this->argument('path');
        $interval = (float) $this->option('interval');

        $this->ensureDirExists();

        $this->logInfo("Starting file watcher with interval '$interval' on: $this->path");
        $this->logInfo('Press Ctrl+C to stop.');

        if (! $this->performInitialScan()) {
            return SymfonyCommand::FAILURE;
        }

        try {
            Loop::addPeriodicTimer($interval, fn () => $this->scanFiles());
            Loop::run();
        } catch (\Exception $e) {
            $this->error("An error occurred while watching the directory: {$e->getMessage()}");
            Log::error($this->logPrefix."Error: {$e->getMessage()}");

            return SymfonyCommand::FAILURE;
        }

        return SymfonyCommand::SUCCESS;
    }

    private function ensureDirExists(): void
    {
        if (! is_dir($this->path)) {
            mkdir($this->path, $this->newDirPermissions, true);
            Log::info("$this->logPrefix Created directory to watch: $this->path");
        }
    }

    /**
     * Perform an initial scan to establish the baseline state without firing events.
     */
    private function performInitialScan(): bool
    {
        try {
            $initialIterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->path, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($initialIterator as $file) {
                /** @var SplFileInfo $file */
                $this->fileTimestamps[$file->getPathname()] = $file->getMTime();
            }
        } catch (UnexpectedValueException $e) {
            $this->error("Failed to perform initial scan on directory: $this->path. Please check permissions.");

            return false;
        }

        $this->info('Initial scan complete. Watching for changes...');

        return true;
    }

    private function scanFiles(): void
    {
        $now = time();

        // Clean old ignore entries (older than 2 seconds for example)
        foreach ($this->ignoreList as $file => $timestamp) {
            if ($now - $timestamp > 2) {
                unset($this->ignoreList[$file]);
            }
        }

        // 1. Get the current state of all files on disk
        $currentFiles = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->path, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            /** @var SplFileInfo $file */
            $path = $file->getPathname();
            $mtime = $file->getMTime();

            // If file is in ignore list, skip
            if (isset($this->ignoreList[$path])) {
                continue;
            }

            $currentFiles[$path] = $mtime;
        }

        // 2. Detect deleted files by comparing the old state with the new one
        $deletedFiles = array_diff_key($this->fileTimestamps, $currentFiles);
        foreach (array_keys($deletedFiles) as $path) {
            $this->logInfo("❌ File deleted: $path");
            $this->dispatchJob(FileChangesEnum::DELETED, $path);
        }

        // 3. Detect new and modified files
        foreach ($currentFiles as $path => $mtime) {
            if (! isset($this->fileTimestamps[$path])) {
                $this->logInfo("✅ File created: $path");
                $this->dispatchJob(FileChangesEnum::CREATED, $path);

            } elseif ($this->fileTimestamps[$path] !== $mtime) {
                $this->logInfo("✏️ File modified: $path");
                $this->dispatchJob(FileChangesEnum::MODIFIED, $path);
            }
        }

        // 4. Update the state for the next scan
        $this->fileTimestamps = $currentFiles;
    }

    private function dispatchJob(FileChangesEnum $fileChange, string $path): void
    {
        if (! Cache::has(self::IGNORE_CACHE_KEY.$path)) {
            ProcessFileChanges::dispatch($fileChange, $path);

            return;
        }

        $this->logInfo("⏳ Ignored file change for: $path");

        // Clear cache so it can be modified again
        Cache::forget(self::IGNORE_CACHE_KEY.$path);
    }

    private function logInfo(string $text): void
    {
        $this->info($text."\n");
        Log::info($this->logPrefix.$text);
    }
}
