<?php

namespace App\Jobs\FileWatcher\FileTypes;

use App\Jobs\FileWatcher\Contracts\AbstractFileType;
use Illuminate\Support\Facades\Log;
use Spatie\Image\Exceptions\CouldNotLoadImage;
use Spatie\Image\Image;
use Spatie\ImageOptimizer\OptimizerChain;
use Spatie\ImageOptimizer\Optimizers\Jpegoptim;

final class ProcessFileJpeg extends AbstractFileType
{
    public function handle(): void
    {
        if (! $this->isCreated() && ! $this->isModified()) {
            Log::info("ProcessFileJson // Skipping file: {$this->filePath} as it is not created or modified.");

            return;
        }

        // get filepath but prepend file with 'optimized_'
        $optimizedFilePath = dirname($this->filePath).DIRECTORY_SEPARATOR.'optimized_'.basename($this->filePath);

        $this
            ->cacheFile($optimizedFilePath)
            ->optimizeImage()
            ?->save($optimizedFilePath);

        Log::info("ProcessFileJpeg // Successfully optimized JPEG file: '$this->filePath' to '$optimizedFilePath'");
    }

    private function optimizeImage(): ?Image
    {
        $optimizerChain = new OptimizerChain()
            ->addOptimizer(new Jpegoptim([
                '--strip-all',
                '--all-progressive',
                '-m85',
            ]))
            ->setTimeout(90);

        try {
            $optimizedImage = Image::load($this->filePath)
                ->optimize($optimizerChain);
        } catch (CouldNotLoadImage $e) {
            Log::error("ProcessFileJpeg // Could not load image: {$this->filePath}. Error: {$e->getMessage()}");

            return null;
        }

        return $optimizedImage;
    }
}
