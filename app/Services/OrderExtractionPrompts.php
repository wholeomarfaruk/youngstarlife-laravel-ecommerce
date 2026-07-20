<?php

namespace App\Services;

trait OrderExtractionPrompts
{
    private const EXTRACTION_SCHEMA = [
        'type' => 'object',
        'properties' => [
            'customer' => [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => ['string', 'null']],
                    'phone' => ['type' => ['string', 'null']],
                    'alternative_phone' => ['type' => ['string', 'null']],
                    'address' => ['type' => ['string', 'null']],
                    'district' => ['type' => ['string', 'null']],
                    'area' => ['type' => ['string', 'null']],
                ],
                'required' => ['name', 'phone', 'alternative_phone', 'address', 'district', 'area'],
                'additionalProperties' => false,
            ],
            'products' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'product_name' => ['type' => 'string'],
                        'quantity' => ['type' => ['integer', 'null']],
                        'size' => ['type' => ['string', 'null']],
                        'color' => ['type' => ['string', 'null']],
                        'variant' => ['type' => ['string', 'null']],
                    ],
                    'required' => ['product_name', 'quantity', 'size', 'color', 'variant'],
                    'additionalProperties' => false,
                ],
            ],
            'order' => [
                'type' => 'object',
                'properties' => [
                    'payment_method' => ['type' => ['string', 'null']],
                    'courier' => ['type' => ['string', 'null']],
                    'delivery_note' => ['type' => ['string', 'null']],
                    'customer_note' => ['type' => ['string', 'null']],
                    'amount_to_collect' => ['type' => ['number', 'null']],
                ],
                'required' => ['payment_method', 'courier', 'delivery_note', 'customer_note', 'amount_to_collect'],
                'additionalProperties' => false,
            ],
            'confidence' => ['type' => 'number'],
            'warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
        'required' => ['customer', 'products', 'order', 'confidence', 'warnings'],
        'additionalProperties' => false,
    ];

    private const RESOLVER_SCHEMA = [
        'type' => 'object',
        'properties' => [
            'matched_id' => ['type' => ['integer', 'null']],
            'reasoning' => ['type' => 'string'],
        ],
        'required' => ['matched_id', 'reasoning'],
        'additionalProperties' => false,
    ];

    private const EXTRACTOR_SYSTEM_PROMPT = <<<'PROMPT'
You are a professional AI Order Extraction Engine for a Laravel Ecommerce System.

Extract customer and order information from plain text, WhatsApp chat, or Facebook
Messenger chat (as text, already OCR'd if it came from an image).

DO NOT invent information. DO NOT guess missing values. If something is missing,
return null. Never attempt to match products against any catalog - that is a
separate step. Ignore greetings (Hi, Assalamu Alaikum, Hello, Need info, Price?).
Ignore seller messages - extract ONLY customer order information. Extract Bangla
and English. Normalize phone numbers to local format (e.g. 8801712345678 or
01712345678 -> 01712345678). Do not translate product names, keep original text.
If multiple products exist, return all of them. If the same product is repeated
with different quantities/colors (e.g. "2ta Black, 1ta Blue" after a product
name), split into separate product entries each with the same product_name.
If address is incomplete, keep whatever parts are available. The address field
must always contain the COMPLETE address exactly as written in the source
(house/road/village, area, city/district - all of it), as a single line of
text with no line breaks - if the source text/chat has the address split
across multiple lines, join every line into one line separated by commas or
spaces. Never drop or omit any part of the address (including city/district
names) when building this field, even though district and area are also
captured separately below. Lower confidence and add a warning string for any
ambiguity you encounter. If the customer or seller mentions a total amount to
collect / cash on delivery amount / total bill (e.g. "Total: 1670", "COD 1670
Tk", "Amount to collect: 1670"), extract it as amount_to_collect (a plain
number, no currency symbol). If no such amount is explicitly stated, return
null - do not calculate it yourself.

Respond with ONLY valid JSON matching the given schema. No markdown, no extra text.
PROMPT;

    private const RESOLVER_SYSTEM_PROMPT = <<<'PROMPT'
You are a product-matching resolver. You will be given a raw extracted product
description (name, and possibly color/size) and a list of candidate products
from the real catalog (id + name). Pick the single best matching candidate id,
or null if none of them plausibly match. Never invent a product outside the
given candidate list.

Respond with ONLY valid JSON matching the given schema. No markdown, no extra text.
PROMPT;

    private function extractionSchema(): array
    {
        return self::EXTRACTION_SCHEMA;
    }

    private function resolverSchema(): array
    {
        return self::RESOLVER_SCHEMA;
    }

    private function extractorSystemPrompt(): string
    {
        return self::EXTRACTOR_SYSTEM_PROMPT;
    }

    private function resolverSystemPrompt(): string
    {
        return self::RESOLVER_SYSTEM_PROMPT;
    }
}
