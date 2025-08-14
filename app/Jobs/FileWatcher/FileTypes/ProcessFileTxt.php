<?php

namespace App\Jobs\FileWatcher\FileTypes;

use App\Jobs\FileWatcher\Contracts\AbstractFileType;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class ProcessFileTxt extends AbstractFileType
{
    public function handle(): void
    {
        if (! $this->isCreated() && ! $this->isModified()) {
            Log::info("ProcessFileTxt // Skipping file: {$this->filePath} as it is not created or modified.");

            return;
        }

        // fetch sample data from https://baconipsum.com/api/?type=meat-and-filler
        try {
            $textResponse = Http::get('https://baconipsum.com/api/?type=meat-and-filler')->json();
        } catch (ConnectionException $e) {
            Log::error("ProcessFileTxt // Failed to fetch sample text: {$e->getMessage()}");

            return;
        }

        // get random element from $appendContent
        $randomIndex = array_rand($textResponse);
        $contentToAppend = $textResponse[$randomIndex];

        $this->cacheFile();

        // Write the updated content back to the file
        file_put_contents($this->filePath, $contentToAppend, FILE_APPEND);
    }
}
