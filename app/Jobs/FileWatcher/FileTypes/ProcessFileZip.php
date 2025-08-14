<?php

namespace App\Jobs\FileWatcher\FileTypes;

use App\Jobs\FileWatcher\Contracts\AbstractFileType;
use Illuminate\Support\Facades\Log;
use ZipArchive;

final class ProcessFileZip extends AbstractFileType
{
    public function handle(): void
    {
        if (! $this->isCreated() && ! $this->isModified()) {
            Log::info("ProcessFileZip // Skipping file: {$this->filePath} as it is not created or modified.");

            return;
        }

        $zip = new ZipArchive();

        if ($zip->open($this->filePath) !== true) {
            Log::error("ProcessFileZip // Failed to open zip file: {$this->filePath}");

            return;
        }

        // remove .zip from the original filePath
        $extractionPath = rtrim($this->filePath, '.zip');

        $this->cacheFile($extractionPath);

        $zip->extractTo($extractionPath);
        $zip->close();
        Log::info("ProcessFileZip // Successfully extracted zip file: {$this->filePath}");

        // Delete original zip
        $this->cacheFile();

        // Delete the original zip file
        if (file_exists($this->filePath)) {
            unlink($this->filePath);
            Log::info("ProcessFileZip // Deleted original zip file: {$this->filePath}");
        } else {
            Log::warning("ProcessFileZip // Original zip file not found for deletion: {$this->filePath}");
        }
    }
}
