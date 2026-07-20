<?php

namespace App\Services;

interface OrderExtractionServiceInterface
{
    public function extractFromText(string $text): array;

    /**
     * @param array<int, array{path: string, media_type: string}> $images
     */
    public function extractFromImages(array $images, string $text = ''): array;

    public function resolveProductMatch(string $rawProductName, array $candidates): ?int;
}
