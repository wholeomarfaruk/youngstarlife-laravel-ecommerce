<?php

namespace App\Services;

use App\Exceptions\ClaudeExtractionException;
use Illuminate\Support\Facades\Http;

class GeminiOrderExtractionService implements OrderExtractionServiceInterface
{
    use OrderExtractionPrompts;

    private const API_URL_TEMPLATE = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

    public function extractFromText(string $text): array
    {
        $body = [
            'system_instruction' => [
                'parts' => [['text' => $this->extractorSystemPrompt()]],
            ],
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $text]]],
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'responseSchema' => $this->toGeminiSchema($this->extractionSchema()),
            ],
        ];

        return $this->callGemini($body);
    }

    public function extractFromImages(array $images, string $text = ''): array
    {
        $parts = [
            ['text' => trim($text) !== ''
                ? "Extract the customer order information from the attached chat screenshot(s) and the following notes:\n\n" . $text
                : 'Extract the customer order information from the attached chat screenshot(s).'],
        ];

        foreach ($images as $image) {
            $parts[] = [
                'inline_data' => [
                    'mime_type' => $image['media_type'],
                    'data' => base64_encode(file_get_contents($image['path'])),
                ],
            ];
        }

        $body = [
            'system_instruction' => [
                'parts' => [['text' => $this->extractorSystemPrompt()]],
            ],
            'contents' => [
                ['role' => 'user', 'parts' => $parts],
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'responseSchema' => $this->toGeminiSchema($this->extractionSchema()),
            ],
        ];

        return $this->callGemini($body);
    }

    public function resolveProductMatch(string $rawProductName, array $candidates): ?int
    {
        $body = [
            'system_instruction' => [
                'parts' => [['text' => $this->resolverSystemPrompt()]],
            ],
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [[
                        'text' => json_encode([
                            'raw_product' => $rawProductName,
                            'candidates' => $candidates,
                        ]),
                    ]],
                ],
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'responseSchema' => $this->toGeminiSchema($this->resolverSchema()),
            ],
        ];

        $result = $this->callGemini($body);

        return $result['matched_id'] ?? null;
    }

    /**
     * Gemini's schema dialect doesn't support "type": ["string", "null"] unions
     * or additionalProperties - strip those down to plain nullable fields.
     */
    private function toGeminiSchema(array $schema): array
    {
        unset($schema['additionalProperties']);

        if (isset($schema['type']) && is_array($schema['type'])) {
            $types = array_diff($schema['type'], ['null']);
            $schema['type'] = strtoupper((string) reset($types));
            $schema['nullable'] = true;
        } elseif (isset($schema['type'])) {
            $schema['type'] = strtoupper($schema['type']);
        }

        if (isset($schema['properties']) && is_array($schema['properties'])) {
            foreach ($schema['properties'] as $key => $value) {
                $schema['properties'][$key] = $this->toGeminiSchema($value);
            }
        }

        if (isset($schema['items']) && is_array($schema['items'])) {
            $schema['items'] = $this->toGeminiSchema($schema['items']);
        }

        return $schema;
    }

    private function callGemini(array $body): array
    {
        $apiKey = config('services.gemini.key');
        $model = config('services.gemini.model');

        if (!$apiKey) {
            throw new ClaudeExtractionException('Gemini API key is not configured.');
        }

        $url = sprintf(self::API_URL_TEMPLATE, $model);

        $response = Http::withHeaders([
            'x-goog-api-key' => $apiKey,
            'content-type' => 'application/json',
        ])->post($url, $body);

        if (!$response->successful()) {
            throw new ClaudeExtractionException('Gemini API request failed: ' . $response->body());
        }

        $content = $response->json('candidates.0.content.parts.0.text');

        if (!$content) {
            throw new ClaudeExtractionException('Gemini API returned an unexpected response shape.');
        }

        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ClaudeExtractionException('Failed to parse JSON from Gemini response.');
        }

        return $decoded;
    }
}
