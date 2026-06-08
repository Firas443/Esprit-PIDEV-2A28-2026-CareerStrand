<?php

class SkillAssessmentService
{
    private string $apiUrl       = 'https://router.huggingface.co/v1/chat/completions';
    private string $defaultModel = 'Qwen/Qwen2.5-7B-Instruct';

    private function apiKey(): string
    {
        $key = getenv('HF_TOKEN')
            ?: getenv('HUGGINGFACE_HUB_TOKEN')
            ?: getenv('HUGGINGFACE_API_KEY')
            ?: ($_SERVER['HF_TOKEN'] ?? '')
            ?: ($_SERVER['HUGGINGFACE_HUB_TOKEN'] ?? '')
            ?: ($_SERVER['HUGGINGFACE_API_KEY'] ?? '');

        if ($key === '' && defined('HF_TOKEN'))              { $key = (string) constant('HF_TOKEN'); }
        if ($key === '' && defined('HUGGINGFACE_HUB_TOKEN')) { $key = (string) constant('HUGGINGFACE_HUB_TOKEN'); }
        if ($key === '' && defined('HUGGINGFACE_API_KEY'))   { $key = (string) constant('HUGGINGFACE_API_KEY'); }

        if ($key === '' && class_exists('config')) {
            try { $key = config::getHuggingFaceApiKey(); } catch (Throwable $e) {}
        }

        return trim($key);
    }

    private function model(): string
    {
        $env = getenv('HF_INTERVIEW_MODEL') ?: ($_SERVER['HF_INTERVIEW_MODEL'] ?? '');
        return trim((string) $env) !== '' ? trim((string) $env) : $this->defaultModel;
    }

    // ── Public API ────────────────────────────────────────────────────────────

    public function generateQuestions(string $skillName): array
    {
        $instructions = <<<'INST'
You are a senior technical assessor evaluating someone's proficiency in a skill.
Generate exactly 5 assessment questions to accurately gauge their knowledge level.

Mix the types:
- 2 practical questions (how would you implement / what would you do)
- 2 conceptual questions (explain how X works / difference between X and Y)
- 1 scenario question (you encounter this problem, how do you solve it)

Rules:
- Each question must directly test the specified skill, not generic knowledge
- Range from basic to intermediate difficulty
- Each question must be 1-2 sentences, no numbering inside the text

Return ONLY a raw JSON object, no markdown, no text before or after:
{"questions":["q1","q2","q3","q4","q5"],"topics":["topic1","topic2","topic3"],"intro":"One welcoming sentence for this skill assessment"}
INST;

        $input = "SKILL TO ASSESS: {$skillName}";

        $json = $this->requestJson($instructions, $input, 600);
        if (is_array($json) && isset($json['questions']) && count((array) $json['questions']) >= 3) {
            return $this->mergeQuestions($skillName, $json);
        }

        return $this->fallbackQuestions($skillName);
    }

    public function evaluateAnswers(string $skillName, array $questions, array $answers): array
    {
        $transcript = [];
        foreach ($questions as $i => $q) {
            $transcript[] = [
                'question' => trim((string) $q),
                'answer'   => trim((string) ($answers[$i] ?? '')),
            ];
        }

        $instructions = <<<'INST'
You are a strict but supportive technical evaluator scoring a skill proficiency self-assessment.
Score each answer based on technical accuracy, depth, practical understanding, and clarity.
Empty or skipped answers get score 0.

Thresholds: 0-30 = Beginner, 31-60 = Intermediate, 61-80 = Advanced, 81-100 = Expert
Calculate an overall score as a weighted average of all question scores.

Return ONLY a raw JSON object, no markdown, no text before or after:
{"score":72,"level":"Intermediate","summary":"2-3 sentence overall evaluation","strengths":["strength1","strength2"],"gaps":["gap1","gap2"],"improvements":["improvement1","improvement2"],"questionFeedback":[{"question":"q","answer":"a","score":70,"feedback":"specific constructive feedback"}],"nextStep":"One concrete actionable learning recommendation"}
INST;

        $input = implode("\n\n", [
            'SKILL BEING ASSESSED: ' . $skillName,
            'ASSESSMENT TRANSCRIPT: ' . json_encode($transcript, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        $json = $this->requestJson($instructions, $input, 1000);
        if (is_array($json) && isset($json['score'])) {
            return $this->mergeEvaluation($skillName, $questions, $answers, $json);
        }

        return $this->fallbackEvaluation($skillName, $questions, $answers);
    }

    // ── Merge helpers ─────────────────────────────────────────────────────────

    private function mergeQuestions(string $skillName, array $json): array
    {
        $questions = $this->normalizeTextList($json['questions'] ?? []);
        $topics    = $this->normalizeTextList($json['topics']    ?? []);
        $fallback  = $this->fallbackQuestions($skillName);

        if (count($questions) < 3) { $questions = $fallback['questions']; }
        if (count($topics)    < 2) { $topics    = $fallback['topics']; }

        $intro = trim((string) ($json['intro'] ?? ''));
        if ($intro === '') { $intro = $fallback['intro']; }

        return [
            'success'   => true,
            'skillName' => $skillName,
            'intro'     => $intro,
            'questions' => array_slice($questions, 0, 5),
            'topics'    => array_slice($topics,    0, 3),
            'source'    => 'ai',
        ];
    }

    private function fallbackQuestions(string $skillName): array
    {
        return [
            'success'   => true,
            'skillName' => $skillName,
            'intro'     => "Let's assess your {$skillName} proficiency. Answer each question as thoroughly as you can — specific examples are your friend.",
            'questions' => [
                "What is {$skillName} and what problems does it solve? Give a practical example of when you would use it.",
                "Describe the most complex thing you have built or solved using {$skillName}. What challenges did you face?",
                "What are the core concepts or principles you need to understand to work effectively with {$skillName}?",
                "How do you debug or troubleshoot common issues when working with {$skillName}? Walk through your process.",
                "How would you rate your current {$skillName} proficiency compared to industry standards, and what would you still want to learn?",
            ],
            'topics' => [
                "{$skillName} fundamentals",
                "Practical application",
                "Problem-solving approach",
            ],
            'source' => 'fallback',
        ];
    }

    private function mergeEvaluation(string $skillName, array $questions, array $answers, array $json): array
    {
        $score = $this->clampScore($json['score'] ?? 0);
        $level = $this->levelFromScore($score);

        $aiLevel      = trim((string) ($json['level'] ?? ''));
        $validLevels  = ['Beginner', 'Intermediate', 'Advanced', 'Expert'];
        if (in_array($aiLevel, $validLevels, true)) { $level = $aiLevel; }

        $questionFeedback = [];
        foreach ((array) ($json['questionFeedback'] ?? []) as $index => $row) {
            if (!is_array($row)) { continue; }
            $questionFeedback[] = [
                'question' => trim((string) ($row['question'] ?? ($questions[$index] ?? ''))),
                'answer'   => trim((string) ($row['answer']   ?? ($answers[$index]   ?? ''))),
                'score'    => $this->clampScore($row['score'] ?? 0),
                'feedback' => trim((string) ($row['feedback'] ?? '')),
            ];
        }

        if (empty($questionFeedback)) {
            return $this->fallbackEvaluation($skillName, $questions, $answers);
        }

        $summary  = trim((string) ($json['summary']  ?? ''));
        $nextStep = trim((string) ($json['nextStep'] ?? ''));

        return [
            'success'          => true,
            'skillName'        => $skillName,
            'score'            => $score,
            'level'            => $level,
            'summary'          => $summary  !== '' ? $summary  : 'Assessment complete.',
            'strengths'        => $this->normalizeTextList($json['strengths']    ?? []),
            'gaps'             => $this->normalizeTextList($json['gaps']         ?? []),
            'improvements'     => $this->normalizeTextList($json['improvements'] ?? []),
            'questionFeedback' => $questionFeedback,
            'nextStep'         => $nextStep !== '' ? $nextStep : 'Keep practising to reach the next level.',
            'source'           => 'ai',
        ];
    }

    private function fallbackEvaluation(string $skillName, array $questions, array $answers): array
    {
        $score    = 0;
        $feedback = [];
        foreach ($questions as $index => $question) {
            $answer = trim((string) ($answers[$index] ?? ''));
            if ($answer === '') {
                $feedback[] = [
                    'question' => trim((string) $question),
                    'answer'   => '',
                    'score'    => 0,
                    'feedback' => 'This question was skipped.',
                ];
                continue;
            }
            $q = 35;
            if (preg_match('/\b(i|my|me|we)\b/i', $answer))                                              { $q += 10; }
            if (preg_match('/\b(example|because|result|built|solved|worked|used|created)\b/i', $answer)) { $q += 15; }
            if (strlen($answer) > 100) { $q += 10; }
            if (strlen($answer) > 250) { $q += 10; }
            $score += $q;
            $feedback[] = [
                'question' => trim((string) $question),
                'answer'   => $answer,
                'score'    => max(0, min(100, $q)),
                'feedback' => 'Include one concrete example and explain the outcome to strengthen your answer.',
            ];
        }

        $overall = count($feedback) ? (int) round($score / count($feedback)) : 0;

        return [
            'success'          => true,
            'skillName'        => $skillName,
            'score'            => $overall,
            'level'            => $this->levelFromScore($overall),
            'summary'          => "Your {$skillName} assessment is complete. Your answers showed some understanding, but adding specific examples and technical depth would improve your score.",
            'strengths'        => ['You provided answers for all questions.', 'Your responses show engagement with the topic.'],
            'gaps'             => ['Specific technical examples were missing', 'More depth in explanations needed'],
            'improvements'     => [
                "Practice explaining {$skillName} concepts with concrete real-world examples.",
                "Build a small project using {$skillName} to deepen your practical understanding.",
                "Compare your approach against best practices in the community.",
            ],
            'questionFeedback' => $feedback,
            'nextStep'         => "Spend focused time building practical projects with {$skillName} to solidify your understanding.",
            'source'           => 'fallback',
        ];
    }

    // ── HTTP helpers ──────────────────────────────────────────────────────────

    private function requestJson(string $instructions, string $input, int $maxOutputTokens): array|null
    {
        try {
            $text = $this->requestText($instructions, $input, $maxOutputTokens, true);
            $json = $this->extractJson($text);
            if (is_array($json)) { return $json; }
        } catch (Throwable $e) {}

        try {
            $text = $this->requestText($instructions, $input, $maxOutputTokens, false);
            $json = $this->extractJson($text);
            return is_array($json) ? $json : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    private function requestText(string $instructions, string $input, int $maxOutputTokens, bool $seedJson = false): string
    {
        $apiKey = $this->apiKey();
        if ($apiKey === '') {
            throw new RuntimeException('HuggingFace API key is not configured.');
        }
        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL extension is not enabled.');
        }

        $messages = [
            ['role' => 'system', 'content' => $instructions],
            ['role' => 'user',   'content' => $input],
        ];
        if ($seedJson) {
            $messages[] = ['role' => 'assistant', 'content' => '{'];
        }

        $payload = [
            'model'       => $this->model(),
            'messages'    => $messages,
            'max_tokens'  => $maxOutputTokens,
            'temperature' => 0.5,
        ];

        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_TIMEOUT        => 12,
            CURLOPT_CONNECTTIMEOUT => 4,
        ]);

        $raw       = curl_exec($ch);
        $status    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $raw === '') {
            throw new RuntimeException($curlError !== '' ? 'cURL: ' . $curlError : 'No response from HuggingFace.');
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new RuntimeException('Invalid JSON response from HuggingFace.');
        }
        if ($status < 200 || $status >= 300) {
            $message = $data['error']['message'] ?? $data['error'] ?? 'HuggingFace request failed (HTTP ' . $status . ').';
            throw new RuntimeException(is_string($message) ? $message : json_encode($message));
        }

        $text = $this->responseText($data);
        if ($seedJson && !str_starts_with(ltrim($text), '{')) {
            $text = '{' . $text;
        }

        return $text;
    }

    private function responseText(array $data): string
    {
        $message = $data['choices'][0]['message']['content'] ?? null;
        if (is_string($message) && trim($message) !== '') { return trim($message); }

        $text = $data['choices'][0]['text'] ?? null;
        if (is_string($text) && trim($text) !== '') { return trim($text); }

        $gen = $data['generated_text'] ?? null;
        if (is_string($gen) && trim($gen) !== '') { return trim($gen); }

        throw new RuntimeException('HuggingFace response contained no text.');
    }

    private function extractJson(string $text): array|null
    {
        $decoded = json_decode($text, true);
        if (is_array($decoded)) { return $decoded; }

        $stripped = preg_replace('/```(?:json)?\s*([\s\S]*?)\s*```/', '$1', $text);
        if ($stripped !== null && $stripped !== $text) {
            $decoded = json_decode(trim($stripped), true);
            if (is_array($decoded)) { return $decoded; }
        }

        $start = strpos($text, '{');
        $end   = strrpos($text, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $candidate = substr($text, $start, $end - $start + 1);
            $decoded   = json_decode($candidate, true);
            if (is_array($decoded)) { return $decoded; }
        }

        return null;
    }

    // ── Utility helpers ───────────────────────────────────────────────────────

    private function levelFromScore(int $score): string
    {
        if ($score >= 81) { return 'Expert'; }
        if ($score >= 61) { return 'Advanced'; }
        if ($score >= 31) { return 'Intermediate'; }
        return 'Beginner';
    }

    private function normalizeTextList(mixed $items): array
    {
        if (!is_array($items)) { return []; }
        $result = [];
        foreach ($items as $item) {
            if (is_string($item) || is_numeric($item)) {
                $text = trim((string) $item);
                if ($text !== '') { $result[] = $text; }
            }
        }
        return $result;
    }

    private function clampScore(mixed $value): int
    {
        return is_numeric($value) ? max(0, min(100, (int) round((float) $value))) : 0;
    }
}
