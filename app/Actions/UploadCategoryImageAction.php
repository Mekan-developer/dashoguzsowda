<?php

namespace App\Actions;

use App\Services\ImageConversionService;
use Illuminate\Http\UploadedFile;

/**
 * Конвертирует загруженное изображение корневой категории в WebP и сохраняет на диск.
 * В отличие от иконки — не попадает ни в какую общую библиотеку, файл уникален для категории.
 */
class UploadCategoryImageAction
{
    private const ASPECT     = 1;
    private const MAX_WIDTH  = 700;
    private const MAX_BYTES  = 200 * 1024;

    public function __construct(
        private readonly ImageConversionService $imageConversion,
    ) {}

    public function execute(UploadedFile $file, float $cropX = 50, float $cropY = 50): string
    {
        return $this->imageConversion->toWebp(
            $file,
            'categories/images',
            aspect: self::ASPECT,
            cropX: $cropX,
            cropY: $cropY,
            maxWidth: self::MAX_WIDTH,
            maxBytes: self::MAX_BYTES,
        );
    }
}
