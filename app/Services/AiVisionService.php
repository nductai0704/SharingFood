<?php

namespace App\Services;

use Google\Cloud\Vision\V1\Client\ImageAnnotatorClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiVisionService
{
    /**
     * Moderate an image using Google Cloud Vision API (with a local fallback if credentials are not set).
     * 
     * @param string $imageUrl Đường dẫn tương đối của ảnh (ví dụ: /storage/food_posts/abc.jpg)
     * @return array ['is_safe' => bool, 'confidence' => float, 'reason' => string]
     */
    public function moderateImage(string $imageUrl): array
    {
        $credentialsPath = env('GOOGLE_APPLICATION_CREDENTIALS');

        // Nếu đã cấu hình file JSON Google Credentials thì chạy Google AI thật
        if ($credentialsPath && file_exists(base_path($credentialsPath))) {
            return $this->moderateWithGoogleVision($imageUrl, base_path($credentialsPath));
        }

        // Nếu chưa cấu hình, ghi log cảnh báo và trả về duyệt an toàn làm mặc định
        Log::warning("Google Application Credentials file not found. Defaulting to safe approval.");
        return [
            'is_safe' => true,
            'confidence' => 0.5,
            'reason' => 'Thiếu tệp cấu hình Google Cloud, tự động phê duyệt an toàn làm mặc định'
        ];
    }

    /**
     * Tích hợp Google Cloud Vision API thật
     */
    protected function moderateWithGoogleVision(string $imageUrl, string $credentialsPath): array
    {
        try {
            Log::info("Calling Google Cloud Vision API for: {$imageUrl}");

            // 1. Khởi tạo Client với Credentials File
            $options = [
                'credentials' => $credentialsPath
            ];

            // Tắt xác thực SSL của Google Vision REST API ở môi trường local để tránh lỗi cURL 60 trên Windows
            if (config('app.env') === 'local') {
                $options['transport'] = 'rest';
                
                $guzzleClient = new \GuzzleHttp\Client([
                    'verify' => false
                ]);
                $httpHandler = \Google\Auth\HttpHandler\HttpHandlerFactory::build($guzzleClient);
                
                $options['transportConfig'] = [
                    'rest' => [
                        'httpHandler' => [$httpHandler, 'async']
                    ]
                ];
            }

            $imageAnnotator = new ImageAnnotatorClient($options);

            // Lấy đường dẫn vật lý tuyệt đối của file ảnh trên server
            // Nếu imageUrl chứa /storage ở đầu, ta chuyển đổi sang đường dẫn public path
            $relativePath = str_replace('/storage/', 'app/public/', $imageUrl);
            $absolutePath = storage_path($relativePath);

            if (!file_exists($absolutePath)) {
                $imageContent = Http::withoutVerifying()->get(url($imageUrl))->body();
            } else {
                $imageContent = file_get_contents($absolutePath);
            }

            // 2. Gửi ảnh lên Google Vision để nhận diện Nhãn (Label Detection) & Nội dung xấu (Safe Search)
            $image = new \Google\Cloud\Vision\V1\Image();
            $image->setContent($imageContent);

            $featureLabel = new \Google\Cloud\Vision\V1\Feature();
            $featureLabel->setType(\Google\Cloud\Vision\V1\Feature\Type::LABEL_DETECTION);

            $featureSafeSearch = new \Google\Cloud\Vision\V1\Feature();
            $featureSafeSearch->setType(\Google\Cloud\Vision\V1\Feature\Type::SAFE_SEARCH_DETECTION);

            $request = new \Google\Cloud\Vision\V1\AnnotateImageRequest();
            $request->setImage($image);
            $request->setFeatures([$featureLabel, $featureSafeSearch]);

            $batchRequest = new \Google\Cloud\Vision\V1\BatchAnnotateImagesRequest();
            $batchRequest->setRequests([$request]);

            $response = $imageAnnotator->batchAnnotateImages($batchRequest);
            $responses = $response->getResponses();

            if (count($responses) === 0) {
                throw new \Exception("Empty response from Google Cloud Vision API.");
            }

            $annotateResponse = $responses[0];
            if ($annotateResponse->getError()) {
                throw new \Exception($annotateResponse->getError()->getMessage());
            }

            // 3. Phân tích nội dung độc hại (Safe Search)
            $safeSearch = $annotateResponse->getSafeSearchAnnotation();
            if ($safeSearch) {
                $adult = $safeSearch->getAdult();       // 1 (Không) -> 5 (Rất khả nghi)
                $violence = $safeSearch->getViolence();
                $racy = $safeSearch->getRacy();

                // Nếu ảnh có yếu tố bạo lực, người lớn hoặc nhạy cảm cao (Cấp độ 4 hoặc 5)
                if ($adult >= 4 || $violence >= 4 || $racy >= 4) {
                    $imageAnnotator->close();
                    return [
                        'is_safe' => false,
                        'confidence' => 0.99,
                        'reason' => 'Google Vision phát hiện hình ảnh không an toàn (Chứa nội dung người lớn/Bạo lực/Gợi dục).'
                    ];
                }
            }

            // 4. Phân tích xem ảnh có liên quan đến thực phẩm hay không (Label Detection)
            $labels = $annotateResponse->getLabelAnnotations();
            $isFoodRelated = false;
            $detectedLabels = [];

            // Các từ khoá liên quan tới thực phẩm (Tiếng Anh do Google trả về tiếng Anh)
            $foodKeywords = [
                'food', 'cuisine', 'dish', 'meal', 'produce', 'ingredient', 'beverage', 'drink',
                'fruit', 'vegetable', 'meat', 'seafood', 'cooking', 'snack', 'bakery', 'bread',
                'pastry', 'dessert', 'recipe', 'sweetness', 'tableware', 'cup', 'bottle', 'poultry'
            ];

            foreach ($labels as $label) {
                $description = strtolower($label->getDescription());
                $score = $label->getScore();
                $detectedLabels[] = "{$description} (" . round($score * 100) . "%)";

                if ($score > 0.5) { // Độ tin cậy tối thiểu 50%
                    foreach ($foodKeywords as $keyword) {
                        if (str_contains($description, $keyword)) {
                            $isFoodRelated = true;
                            break 2; // Thoát cả 2 vòng lặp
                        }
                    }
                }
            }

            $imageAnnotator->close();

            Log::info("Google Vision labels for {$imageUrl}: " . implode(', ', $detectedLabels));

            if (!$isFoodRelated) {
                return [
                    'is_safe' => false,
                    'confidence' => 0.95,
                    'reason' => 'Hình ảnh không liên quan đến thực phẩm/đồ ăn. Nhãn nhận diện được: ' . implode(', ', array_slice($detectedLabels, 0, 3))
                ];
            }

            return [
                'is_safe' => true,
                'confidence' => 0.99,
                'reason' => 'Đã nhận diện có đồ ăn/thực phẩm & Nội dung an toàn.'
            ];

        } catch (\Exception $e) {
            Log::error('Error calling Google Cloud Vision API: ' . $e->getMessage());
            // Trả về fallback an toàn nếu kết nối API Google gặp lỗi
            return [
                'is_safe' => true,
                'confidence' => 0.5,
                'reason' => 'Lỗi hệ thống gọi API Google Vision, tự động duyệt an toàn làm mặc định.'
            ];
        }
    }
}
