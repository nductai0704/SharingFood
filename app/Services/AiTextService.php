<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiTextService
{
    /**
     * Categorize food items into 'dry' and 'fresh' using Gemini AI.
     * 
     * @param array $items Array of food item names
     * @return array associative array with 'dry' and 'fresh' keys containing the items
     */
    public function categorizeFoodItems(array $items): array
    {
        $apiKey = env('GEMINI_API_KEY');
        
        if (empty($apiKey)) {
            Log::warning("GEMINI_API_KEY not found in .env. Falling back to basic rule-based categorization.");
            return $this->fallbackCategorization($items);
        }

        try {
            $prompt = "Bạn là một chuyên gia về logistic và thực phẩm. Tôi có danh sách các món đồ từ thiện sau:\n" 
                . json_encode($items, JSON_UNESCAPED_UNICODE) 
                . "\n\nHãy phân loại chúng vào 2 nhóm:\n"
                . "1. 'dry': Đồ khô, đồ hộp, gạo, mì gói, nước đóng chai, nhu yếu phẩm không dễ hỏng (có thể để được qua 48 tiếng ở điều kiện thường).\n"
                . "2. 'fresh': Đồ tươi sống, rau củ quả, trái cây, thịt cá, suất ăn nấu chín, bánh mì ngọt, thực phẩm dễ ôi thiu (chỉ nên để tối đa 24 tiếng).\n\n"
                . "CHỈ TRẢ VỀ JSON thuần túy (không có markdown code block như ```json). Định dạng mong muốn:\n"
                . '{"dry": ["item1", "item2"], "fresh": ["item3", "item4"]}';

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.1,
                    'responseMimeType' => 'application/json'
                ]
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                    $jsonText = $data['candidates'][0]['content']['parts'][0]['text'];
                    $jsonText = trim(str_replace(['```json', '```'], '', $jsonText));
                    $result = json_decode($jsonText, true);
                    
                    if (json_last_error() === JSON_ERROR_NONE && isset($result['dry']) && isset($result['fresh'])) {
                        return $result;
                    }
                }
            }
            
            Log::error("Gemini API error or invalid format", ['response' => $response->body()]);
            return $this->fallbackCategorization($items);

        } catch (\Exception $e) {
            Log::error("Error calling Gemini API: " . $e->getMessage());
            return $this->fallbackCategorization($items);
        }
    }

    /**
     * Fallback method if Gemini is unavailable
     */
    private function fallbackCategorization(array $items): array
    {
        $dry = [];
        $fresh = [];
        
        $freshKeywords = ['rau', 'củ', 'quả', 'thịt', 'cá', 'cơm', 'bánh mì', 'trái cây', 'suất ăn', 'tươi', 'sống', 'sữa chua', 'sữa tươi', 'hộp cơm'];
        
        foreach ($items as $item) {
            $isFresh = false;
            $lowerItem = mb_strtolower($item);
            
            foreach ($freshKeywords as $keyword) {
                if (str_contains($lowerItem, $keyword)) {
                    $isFresh = true;
                    break;
                }
            }
            
            if ($isFresh) {
                $fresh[] = $item;
            } else {
                $dry[] = $item;
            }
        }
        
        return [
            'dry' => $dry,
            'fresh' => $fresh
        ];
    }
}
