<?php

namespace App\Services;

use App\Exceptions\ClaudeExtractionException;
use Illuminate\Support\Facades\Http;

class OpenAiOrderExtractionService implements OrderExtractionServiceInterface
{
    use OrderExtractionPrompts;

    private const API_URL = 'https://api.openai.com/v1/chat/completions';

    public function extractFromText(string $text): array
    {
        $body = [
            'model' => config('services.openai.model'),
            'messages' => [
                ['role' => 'system', 'content' => $this->extractorSystemPrompt()],
                ['role' => 'user', 'content' => $text],
            ],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => ['name' => 'order_extraction', 'strict' => true, 'schema' => $this->extractionSchema()],
            ],
        ];

        return $this->callOpenAi($body);
    }

    public function extractFromImages(array $images, string $text = ''): array
    {
        $content = [
            [
                'type' => 'text',
                'text' => trim($text) !== ''
                    ? "Extract the customer order information from the attached chat screenshot(s) and the following notes:\n\n" . $text
                    : 'Extract the customer order information from the attached chat screenshot(s).',
            ],
        ];

        foreach ($images as $image) {
            $base64 = base64_encode(file_get_contents($image['path']));
            $content[] = [
                'type' => 'image_url',
                'image_url' => ['url' => "data:{$image['media_type']};base64,{$base64}"],
            ];
        }

        $body = [
            'model' => config('services.openai.model'),
            'messages' => [
                ['role' => 'system', 'content' => $this->extractorSystemPrompt()],
                ['role' => 'user', 'content' => $content],
            ],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => ['name' => 'order_extraction', 'strict' => true, 'schema' => $this->extractionSchema()],
            ],
        ];

        return $this->callOpenAi($body);
    }

    public function resolveProductMatch(string $rawProductName, array $candidates): ?int
    {
        $body = [
            'model' => config('services.openai.model'),
            'messages' => [
                ['role' => 'system', 'content' => $this->resolverSystemPrompt()],
                [
                    'role' => 'user',
                    'content' => json_encode([
                        'raw_product' => $rawProductName,
                        'candidates' => $candidates,
                    ]),
                ],
            ],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => ['name' => 'product_resolution', 'strict' => true, 'schema' => $this->resolverSchema()],
            ],
        ];

        $result = $this->callOpenAi($body);

        return $result['matched_id'] ?? null;
    }

    private function callOpenAi(array $body): array
    {
        $apiKey = config('services.openai.key');

        if (!$apiKey) {
            throw new ClaudeExtractionException('OpenAI API key is not configured.');
        }

        $response = Http::withToken($apiKey)
            ->withHeaders(['content-type' => 'application/json'])
            ->post(self::API_URL, $body);

        if (!$response->successful()) {
            throw new ClaudeExtractionException('OpenAI API request failed: ' . $response->body());
        }

        $content = $response->json('choices.0.message.content');

        if (!$content) {
            throw new ClaudeExtractionException('OpenAI API returned an unexpected response shape.');
        }

        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ClaudeExtractionException('Failed to parse JSON from OpenAI response.');
        }

        return $decoded;
    }
}
