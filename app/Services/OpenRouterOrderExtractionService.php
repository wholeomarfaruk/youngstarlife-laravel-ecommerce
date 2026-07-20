<?php

namespace App\Services;

use App\Exceptions\ClaudeExtractionException;
use Illuminate\Support\Facades\Http;

class OpenRouterOrderExtractionService implements OrderExtractionServiceInterface
{
    use OrderExtractionPrompts;

    private const API_URL = 'https://openrouter.ai/api/v1/chat/completions';
    private const EXTRACTION_MAX_TOKENS = 1024;
    private const RESOLVER_MAX_TOKENS = 256;

    public function extractFromText(string $text): array
    {
        $body = [
            'model' => config('services.openrouter.model'),
            'max_tokens' => self::EXTRACTION_MAX_TOKENS,
            'messages' => [
                ['role' => 'system', 'content' => $this->extractorSystemPrompt()],
                ['role' => 'user', 'content' => $text],
            ],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => ['name' => 'order_extraction', 'strict' => true, 'schema' => $this->extractionSchema()],
            ],
        ];

        return $this->callOpenRouter($body);
    }

    public function extractFromImages(array $images, string $text = ''): array
    {
        $content = [
            [
                'type' => 'text',
                'text' => trim($text) !== ''
                    ? "Analyze the attached chat screenshot(s) and the additional notes below.\n\n{$text}\n\nExtract the customer's final order only.\n\nRules:\n- Ignore greetings, emojis, stickers, reactions, and unrelated messages.\n- Ignore seller messages unless they confirm the customer's final order.\n- Use only the customer's latest decision.\n- Do not guess missing information.\n\nQuantity Rules:\n- Default quantity to 1 if not explicitly mentioned.\n- Recognize explicit quantities such as: 1pc, 2pc, 3pc, 1pcs, 2pcs, 3pcs, 1 piece, 2 pieces, qty 2, quantity 2, x2, x3, ২টা, ৩টা, দুইটা, তিনটা.\n- Never interpret a valid size as quantity.\n\nSize Rules:\n- Valid clothing sizes: XS, S, SM, M, L, XL, XXL, XXXL, 2XL, 3XL, 4XL, FREE SIZE.\n- Valid children's sizes: 1Y-16Y.\n- Valid numeric sizes: 26, 28, 30, 32, 34, 36, 38, 40, 42, 44, 46, 48.\n- Sizes may be lowercase or uppercase (e.g. xl, xxl, sm, 2xl, 4y). Normalize them to uppercase.\n- If a valid size is present and quantity is not explicitly mentioned, set quantity to 1.\n- If no size is mentioned, set size to null.\n\nColor & Combo Rules:\n- If colors are joined by '/', '&', '+', ',', 'and', or the word 'combo', treat them as a single product with multiple colors.\n- If colors are listed separately without indicating a combo, treat them as separate products.\n- Preserve color names exactly as written.\n\nProduct Rules:\n- Preserve the product name exactly as written.\n- Do not rename, translate, or infer product names.\n- Return one entry per ordered product.\n\nAmount Rules:\n- If a total/COD/amount to collect is explicitly stated (e.g. \"Total: 1670\", \"COD 1670\", \"Amount to collect: 1670\"), set order.amount_to_collect to that number.\n- If no such amount is stated, set order.amount_to_collect to null. Do not calculate it yourself.\n\nAddress Rules:\n- customer.address must contain the COMPLETE address exactly as written (house/road/village, area, city/district - all of it), as a single line of text with no line breaks.\n- If the address is written across multiple lines in the source, join every line into one line separated by commas or spaces.\n- Never drop or omit any part of the address, including city/district names, even though district and area are also captured separately in customer.district / customer.area.\n\nReturn only valid JSON."
                    : "Analyze the attached chat screenshot(s) and extract the customer's final order only.\n\nRules:\n- Ignore greetings, emojis, stickers, reactions, and unrelated messages.\n- Ignore seller messages unless they confirm the customer's final order.\n- Use only the customer's latest decision.\n- Do not guess missing information.\n\nQuantity Rules:\n- Default quantity to 1 if not explicitly mentioned.\n- Recognize explicit quantities such as: 1pc, 2pc, 3pc, 1pcs, 2pcs, 3pcs, 1 piece, 2 pieces, qty 2, quantity 2, x2, x3, ২টা, ৩টা, দুইটা, তিনটা.\n- Never interpret a valid size as quantity.\n\nSize Rules:\n- Valid clothing sizes: XS, S, SM, M, L, XL, XXL, XXXL, 2XL, 3XL, 4XL, FREE SIZE.\n- Valid children's sizes: 1Y-16Y.\n- Valid numeric sizes: 26, 28, 30, 32, 34, 36, 38, 40, 42, 44, 46, 48.\n- Sizes may be lowercase or uppercase (e.g. xl, xxl, sm, 2xl, 4y). Normalize them to uppercase.\n- If a valid size is present and quantity is not explicitly mentioned, set quantity to 1.\n- If no size is mentioned, set size to null.\n\nColor & Combo Rules:\n- If colors are joined by '/', '&', '+', ',', 'and', or the word 'combo', treat them as a single product with multiple colors.\n- If colors are listed separately without indicating a combo, treat them as separate products.\n- Preserve color names exactly as written.\n\nProduct Rules:\n- Preserve the product name exactly as written.\n- Do not rename, translate, or infer product names.\n- Return one entry per ordered product.\n\nAmount Rules:\n- If a total/COD/amount to collect is explicitly stated (e.g. \"Total: 1670\", \"COD 1670\", \"Amount to collect: 1670\"), set order.amount_to_collect to that number.\n- If no such amount is stated, set order.amount_to_collect to null. Do not calculate it yourself.\n\nAddress Rules:\n- customer.address must contain the COMPLETE address exactly as written (house/road/village, area, city/district - all of it), as a single line of text with no line breaks.\n- If the address is written across multiple lines in the source, join every line into one line separated by commas or spaces.\n- Never drop or omit any part of the address, including city/district names, even though district and area are also captured separately in customer.district / customer.area.\n\nReturn only valid JSON.",
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
            'model' => config('services.openrouter.model'),
            'max_tokens' => self::EXTRACTION_MAX_TOKENS,
            'messages' => [
                ['role' => 'system', 'content' => $this->extractorSystemPrompt()],
                ['role' => 'user', 'content' => $content],
            ],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => ['name' => 'order_extraction', 'strict' => true, 'schema' => $this->extractionSchema()],
            ],
        ];

        return $this->callOpenRouter($body);
    }

    public function resolveProductMatch(string $rawProductName, array $candidates): ?int
    {
        $body = [
            'model' => config('services.openrouter.model'),
            'max_tokens' => self::RESOLVER_MAX_TOKENS,
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

        $result = $this->callOpenRouter($body);

        return $result['matched_id'] ?? null;
    }

    private function callOpenRouter(array $body): array
    {
        $apiKey = config('services.openrouter.key');

        if (!$apiKey) {
            throw new ClaudeExtractionException('OpenRouter API key is not configured.');
        }

        $response = Http::withToken($apiKey)
            ->withHeaders([
                'content-type' => 'application/json',
                'HTTP-Referer' => config('app.url'),
                'X-Title' => config('app.name'),
            ])
            ->post(self::API_URL, $body);

        if (!$response->successful()) {
            throw new ClaudeExtractionException('OpenRouter API request failed: ' . $response->body());
        }

        $content = $response->json('choices.0.message.content');

        if (!$content) {
            throw new ClaudeExtractionException('OpenRouter API returned an unexpected response shape.');
        }

        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ClaudeExtractionException('Failed to parse JSON from OpenRouter response.');
        }

        return $decoded;
    }
}
