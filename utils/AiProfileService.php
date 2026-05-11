<?php

class AiProfileService
{
    private string $apiUrl = 'https://router.huggingface.co/v1/chat/completions';

    private function apiKey(): string
    {
        $key = getenv('HF_TOKEN')
            ?: getenv('HUGGINGFACE_HUB_TOKEN')
            ?: getenv('HUGGINGFACE_API_KEY')
            ?: ($_SERVER['HF_TOKEN'] ?? '')
            ?: ($_SERVER['HUGGINGFACE_HUB_TOKEN'] ?? '')
            ?: ($_SERVER['HUGGINGFACE_API_KEY'] ?? '');

        if ($key === '' && defined('HF_TOKEN')) {
            $key = (string) constant('HF_TOKEN');
        }
        if ($key === '' && defined('HUGGINGFACE_HUB_TOKEN')) {
            $key = (string) constant('HUGGINGFACE_HUB_TOKEN');
        }
        if ($key === '' && defined('HUGGINGFACE_API_KEY')) {
            $key = (string) constant('HUGGINGFACE_API_KEY');
        }

        // Fall back to the key stored in config.php
        if ($key === '' && class_exists('config')) {
            try { $key = config::getHuggingFaceApiKey(); } catch (Throwable $e) {}
        }

        return trim($key);
    }

    private function model(): string
    {
        // config.php is the authoritative source for the model name
        if (class_exists('config')) {
            try {
                $fromConfig = trim(config::getHuggingFaceFeedbackModel());
                if ($fromConfig !== '') {
                    return $fromConfig;
                }
            } catch (Throwable $e) {}
        }

        $model = getenv('HF_MODEL')
            ?: getenv('HUGGINGFACE_MODEL')
            ?: ($_SERVER['HF_MODEL'] ?? '')
            ?: ($_SERVER['HUGGINGFACE_MODEL'] ?? '');

        return trim($model) !== '' ? trim($model) : 'katanemo/Arch-Router-1.5B';
    }

    public function generateQuestions(array $profile): array
    {
        $instructions = implode("\n", [
            'You create thoughtful professional profile questionnaire questions.',
            'Return only valid JSON with this exact shape: {"questions":["...","...","..."]}.',
            'Write exactly 3 short, clear, personalized questions.',
            'Each question should help analyze the person for a stronger CareerStrand bio.',
            'Do not ask for private, sensitive, medical, legal, or financial information.',
        ]);

        $input = 'Create 3 follow-up questions for this profile context: ' .
            json_encode($profile, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $text = $this->requestText($instructions, $input, 450);
        $questions = $this->extractQuestions($text);

        if (!is_array($questions) || count($questions) < 3) {
            $questions = $this->fallbackQuestions($profile);
        }

        return array_slice(array_values(array_map(
            fn($q) => trim((string) $q),
            $questions
        )), 0, 3);
    }

    public function generateBio(array $answers, string $role, string $fullName): string
    {
        $instructions = implode("\n", [
            'You write concise, polished professional bios for CareerStrand profiles.',
            'Analyze the questionnaire answers and turn them into one natural paragraph.',
            'Keep it truthful and specific. Do not invent degrees, employers, awards, or years of experience.',
            'Use third person. Keep the bio under 240 characters because it is saved in a short database field.',
            'Return only the bio text, with no heading and no quotation marks.',
        ]);

        $input = 'Generate a profile bio from this JSON: ' .
            json_encode([
                'fullName' => $fullName,
                'role' => $role,
                'answers' => $answers,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $bio = trim($this->requestText($instructions, $input, 220));
        $bio = preg_replace('/\s+/', ' ', $bio) ?? $bio;
        $bio = trim($bio, " \t\n\r\0\x0B\"'");

        if ($bio === '') {
            throw new RuntimeException('AI returned an empty bio.');
        }

        return function_exists('mb_substr') ? mb_substr($bio, 0, 255) : substr($bio, 0, 255);
    }

    private function requestText(string $instructions, string $input, int $maxOutputTokens): string
    {
        $apiKey = $this->apiKey();
        if ($apiKey === '') {
            throw new RuntimeException('HF_TOKEN or HUGGINGFACE_HUB_TOKEN is not configured.');
        }

        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL extension is not enabled.');
        }

        $payload = [
            'model' => $this->model(),
            'messages' => [
                ['role' => 'system', 'content' => $instructions],
                ['role' => 'user', 'content' => $input],
            ],
            'max_tokens' => $maxOutputTokens,
            'temperature' => 0.4,
        ];

        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_TIMEOUT => 30,
        ]);

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $raw === '') {
            throw new RuntimeException($curlError !== '' ? $curlError : 'No response from Hugging Face.');
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new RuntimeException('Invalid response from Hugging Face.');
        }

        if ($status < 200 || $status >= 300) {
            $message = $data['error']['message'] ?? 'Hugging Face request failed.';
            throw new RuntimeException($message);
        }

        return $this->responseText($data);
    }

    private function responseText(array $data): string
    {
        $message = $data['choices'][0]['message']['content'] ?? null;
        if (is_string($message) && trim($message) !== '') {
            return trim($message);
        }

        $text = $data['choices'][0]['text'] ?? null;
        if (is_string($text) && trim($text) !== '') {
            return trim($text);
        }

        $generatedText = $data['generated_text'] ?? null;
        if (is_string($generatedText) && trim($generatedText) !== '') {
            return trim($generatedText);
        }

        throw new RuntimeException('Hugging Face response did not include text.');
    }

    private function extractQuestions(string $text): array
    {
        $decoded = json_decode($text, true);
        if (is_array($decoded) && isset($decoded['questions']) && is_array($decoded['questions'])) {
            return $decoded['questions'];
        }

        if (preg_match('/\{.*\}/s', $text, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (is_array($decoded) && isset($decoded['questions']) && is_array($decoded['questions'])) {
                return $decoded['questions'];
            }
        }

        if (preg_match('/\[[\s\S]*\]/', $text, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
        $questions = [];
        foreach ($lines as $line) {
            $line = trim($line);
            $line = preg_replace('/^\s*(?:[-*]|\d+[.)])\s*/', '', $line) ?? $line;
            $line = trim($line, " \t\n\r\0\x0B\"'");
            if ($line !== '' && str_contains($line, '?')) {
                $questions[] = $line;
            }
        }

        return $questions;
    }

    private function fallbackQuestions(array $profile): array
    {
        $role = strtolower(trim((string) ($profile['role'] ?? 'user')));
        $field = trim((string) ($profile['field'] ?? 'your field'));

        if ($role === 'manager recruiter') {
            return [
                "What qualities make a candidate stand out to you in {$field}?",
                'How do you evaluate whether someone is ready for an opportunity?',
                'What kind of professional growth do you want to create for candidates?',
            ];
        }

        if ($role === 'manager') {
            return [
                "What type of projects or activities would you like your community to build in {$field}?",
                'How do you support members when they need guidance?',
                'What impact do you want your organization or community to make?',
            ];
        }

        return [
            "What kind of work in {$field} motivates you the most?",
            'Which skill or habit has helped you grow recently?',
            'What opportunity would help you move closer to your career goal?',
        ];
    }
}
?>