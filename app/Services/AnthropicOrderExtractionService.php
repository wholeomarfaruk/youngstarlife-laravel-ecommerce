<?php

namespace App\Services;

use App\Exceptions\ClaudeExtractionException;
use Illuminate\Support\Facades\Http;

class AnthropicOrderExtractionService implements OrderExtractionServiceInterface
{
    use OrderExtractionPrompts;

    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const ANTHROPIC_VERSION = '2023-06-01';

    public function extractFromText(string $text): array
    {
        $body = [
            'model' => config('services.anthropic.model'),
            'max_tokens' => 2048,
            'system' => $this->extractorSystemPrompt(),
            'messages' => [
                ['role' => 'user', 'content' => $text],
            ],
            'output_config' => [
                'format' => ['type' => 'json_schema', 'schema' => $this->extractionSchema()],
            ],
        ];

        return $this->callClaude($body);
    }

    public function extractFromImages(array $images, string $text = ''): array
    {
        $content = [];

        foreach ($images as $image) {
            $content[] = [
                'type' => 'image',
                'source' => [
                    'type' => 'base64',
                    'media_type' => $image['media_type'],
                    'data' => base64_encode(file_get_contents($image['path'])),
                ],
            ];
        }

        $content[] = [
            'type' => 'text',
            'text' => trim($text) !== ''
                ? "Extract the customer order information from the attached chat screenshot(s) and the following notes:\n\n" . $text
                : 'Extract the customer order information from the attached chat screenshot(s).',
        ];

        $body = [
            'model' => config('services.anthropic.model'),
            'max_tokens' => 2048,
            'system' => $this->extractorSystemPrompt(),
            'messages' => [
                ['role' => 'user', 'content' => $content],
            ],
            'output_config' => [
                'format' => ['type' => 'json_schema', 'schema' => $this->extractionSchema()],
            ],
        ];

        return $this->callClaude($body);
    }

    public function resolveProductMatch(string $rawProductName, array $candidates): ?int
    {
        $body = [
            'model' => config('services.anthropic.model'),
            'max_tokens' => 512,
            'system' => $this->resolverSystemPrompt(),
            'messages' => [
                [
                    'role' => 'user',
                    'content' => json_encode([
                        'raw_product' => $rawProductName,
                        'candidates' => $candidates,
                    ]),
                ],
            ],
            'output_config' => [
                'format' => ['type' => 'json_schema', 'schema' => $this->resolverSchema()],
            ],
        ];

        $result = $this->callClaude($body);

        return $result['matched_id'] ?? null;
    }

    private function callClaude(array $body): array
    {
        $apiKey = config('services.anthropic.key');

        if (!$apiKey) {
            throw new ClaudeExtractionException('Anthropic API key is not configured.');
        }

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => self::ANTHROPIC_VERSION,
            'content-type' => 'application/json',
        ])->post(self::API_URL, $body);

        if (!$response->successful()) {
            throw new ClaudeExtractionException('Claude API request failed: ' . $response->body());
        }

        $content = $response->json('content.0.text');

        if (!$content) {
            throw new ClaudeExtractionException('Claude API returned an unexpected response shape.');
        }

        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ClaudeExtractionException('Failed to parse JSON from Claude response.');
        }

        return $decoded;
    }
}
