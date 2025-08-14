<?php

namespace App\Jobs\FileWatcher;

use App\Console\Commands\FileWatcherCommand;
use App\Enums\FileChangesEnum;
use App\Enums\SupportedFileTypes;
use App\Jobs\FileWatcher\Contracts\AbstractFileType;
use App\Jobs\FileWatcher\FileTypes\ProcessFileJpeg;
use App\Jobs\FileWatcher\FileTypes\ProcessFileJson;
use App\Jobs\FileWatcher\FileTypes\ProcessFileTxt;
use App\Jobs\FileWatcher\FileTypes\ProcessFileZip;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessFileChanges implements ShouldQueue
{
    use Queueable;

    private const array MIME_TYPE_JOB_MAPPING = [
        SupportedFileTypes::JPEG->value => [
            ProcessFileJpeg::class,
        ],
        SupportedFileTypes::JSON->value => [
            ProcessFileJson::class,
        ],
        SupportedFileTypes::JSON_LD->value => [
            ProcessFileJson::class,
        ],
        SupportedFileTypes::TXT->value => [
            ProcessFileTxt::class,
        ],
        SupportedFileTypes::ZIP->value => [
            ProcessFileZip::class,
        ],
        SupportedFileTypes::ZIP_COMPRESSED->value => [
            ProcessFileZip::class,
        ],
    ];

    private string $logPrefix = 'ProcessFileChanges // ';

    public function __construct(
        private readonly FileChangesEnum $fileChangeType,
        private readonly string $path,
    ) {
        // nth
    }

    public function handle(): void
    {
        if ($this->fileChangeType === FileChangesEnum::DELETED) {
            $this->handleFileDeletion();

            return;
        }

        $isValidMimeType = SupportedFileTypes::isValid($this->path);
        $mimeType = SupportedFileTypes::getMimeType($this->path);

        $logFileSuffix = "'$this->path' with MIME type: '{$mimeType}'";

        if (! $isValidMimeType) {
            Log::warning("$this->logPrefix Unsupported file type detected: $logFileSuffix");

            return;
        }

        Log::info("$this->logPrefix Valid file type detected: $logFileSuffix");

        // Get the value from MIME_TYPE_JOB_MAPPING based on the files mimetype
        $jobClasses = self::MIME_TYPE_JOB_MAPPING[$mimeType] ?? null;

        if (is_null($jobClasses)) {
            Log::warning("$this->logPrefix No job class found for MIME type: $logFileSuffix");

            return;
        }

        /** @var AbstractFileType $jobClass */
        foreach ($jobClasses as $jobClass) {
            Log::info("$this->logPrefix Dispatching job: '$jobClass' for file: $logFileSuffix");
            dispatch(new $jobClass($this->fileChangeType, $this->path));
        }
    }

    private function handleFileDeletion(): void
    {
        Log::info("$this->logPrefix File deleted: $this->path");

        // Download meme
        try {
            $response = Http::get('https://meme-api.com/gimme')->json();
        } catch (ConnectionException $e) {
            Log::error("$this->logPrefix Failed to connect to meme API: ".$e->getMessage());

            return;
        } catch (\Exception $e) {
            Log::error("$this->logPrefix An error occurred while fetching meme: ".$e->getMessage());

            return;
        }

        // check if array key 'url' exists in the response
        if (! isset($response['url'])) {
            Log::error("$this->logPrefix No meme found to download for file deletion");

            return;
        }

        $memeUrl = $response['url'];

        Log::info("$this->logPrefix Downloading meme from: $memeUrl");

        try {
            $memeResponse = Http::get($memeUrl);
        } catch (ConnectionException $e) {
            Log::error("$this->logPrefix Failed to connect to meme URL: ".$e->getMessage());

            return;
        } catch (\Exception $e) {
            Log::error("$this->logPrefix An error occurred while downloading meme: ".$e->getMessage());

            return;
        }

        if ($memeResponse->successful()) {
            $memeFileExtension = pathinfo($memeUrl, PATHINFO_EXTENSION);

            // save meme to the same path as the deleted file without using storage
            $fileName = basename($this->path);
            $memePath = dirname($this->path).DIRECTORY_SEPARATOR.'meme_'.$fileName.'.'.$memeFileExtension;

            Cache::put(
                key: FileWatcherCommand::IGNORE_CACHE_KEY.$memePath,
                value: true,
            );

            file_put_contents($memePath, $memeResponse->body());
            Log::info("$this->logPrefix Meme downloaded and saved to: $memePath");
        }
    }
}
