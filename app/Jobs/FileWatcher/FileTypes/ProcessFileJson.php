<?php

namespace App\Jobs\FileWatcher\FileTypes;

use App\Jobs\FileWatcher\Contracts\AbstractFileType;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class ProcessFileJson extends AbstractFileType
{
    public function handle(): void
    {
        if (! $this->isCreated() && ! $this->isModified()) {
            Log::info("ProcessFileJson // Skipping file: {$this->filePath} as it is not created or modified.");

            return;
        }

        $fileContent = json_decode(file_get_contents($this->filePath), true);

        try {
            $response = Http::post('https://fswatcher.requestcatcher.com', $fileContent);
            if ($response->failed()) {
                Log::error("ProcessFileJson // Failed to send file content: {$response->body()}");

                return;
            }
            Log::info('ProcessFileJson // Successfully sent file content to the endpoint.');

        } catch (ConnectionException $e) {
            Log::error("ProcessFileJson // Failed to send file content: {$e->getMessage()}");

            return;
        }
    }
}
