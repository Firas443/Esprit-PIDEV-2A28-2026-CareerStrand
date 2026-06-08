<?php

class OpportunityInterviewService
{
    private string $apiUrl = 'https://router.huggingface.co/v1/chat/completions';
    private string $defaultModel = 'Qwen/Qwen2.5-7B-Instruct';

    private function apiKey(): string
    {
        $key = getenv('HF_TOKEN')
            ?: getenv('HUGGINGFACE_HUB_TOKEN')
            ?: getenv('HUGGINGFACE_API_KEY')
            ?: ($_SERVER['HF_TOKEN'] ?? '')
            ?: ($_SERVER['HUGGINGFACE_HUB_TOKEN'] ?? '')
            ?: ($_SERVER['HUGGINGFACE_API_KEY'] ?? '');

        if ($key === '' && defined('HF_TOKEN'))               { $key = (string) constant('HF_TOKEN'); }
        if ($key === '' && defined('HUGGINGFACE_HUB_TOKEN'))  { $key = (string) constant('HUGGINGFACE_HUB_TOKEN'); }
        if ($key === '' && defined('HUGGINGFACE_API_KEY'))    { $key = (string) constant('HUGGINGFACE_API_KEY'); }

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

    public function generateTechnicalQuestions(array $opportunity, array $profile, array $skills = []): array
    {
        $ctx = $this->normalizeContext($opportunity, $profile, $skills);

        // Flat skill/tag list — this is what the user sees as "tags"
        $tagNames   = array_map(fn($s) => $s['skillName'], $ctx['skills']);
        $tagsLine   = $tagNames ? implode(', ', $tagNames) : 'not specified';
        $description = $ctx['description'] !== '' ? $ctx['description'] : 'No description provided.';

        $instructions = <<<'INST'
You are a hiring manager conducting a job interview.
Read the job posting below and generate exactly 5 interview questions you would ask this candidate.

Each question must:
- Directly relate to the job title, job description, or one of the listed skills/tags
- Feel like a real question a hiring manager would ask in an interview for this specific job
- Be concrete and specific — not generic HR questions
- Mix types: at least 2 questions about listed skills/technologies, 1 scenario question, 1 experience question, 1 about their approach to the work described
- Be 1-2 sentences long, no numbering inside the text

Return ONLY a raw JSON object, no markdown, no text before or after:
{"questions":["q1","q2","q3","q4","q5"],"focusAreas":["area1","area2","area3"],"coachNotes":["note1","note2","note3"]}
INST;

        $input = <<<INPUT
JOB TITLE: {$ctx['title']}
JOB TYPE: {$ctx['type']}
CATEGORY: {$ctx['category']}
LEVEL REQUIRED: {$ctx['requiredLevel']}
SKILLS / TAGS: {$tagsLine}
JOB DESCRIPTION: {$description}
INPUT;

        $json = $this->requestJson($instructions, $input, 900);
        if (is_array($json) && isset($json['questions']) && count((array) $json['questions']) >= 3) {
            return $this->mergeTechnicalQuestions($ctx, $json);
        }

        $fallback = $this->fallbackTechnicalQuestions($ctx);
        $fallback['aiError'] = 'The AI could not generate questions for this opportunity right now. These are standard questions based on the role requirements.';
        return $fallback;
    }

    public function evaluateInterview(array $opportunity, array $profile, array $skills, array $questions, array $answers): array
    {
        $ctx = $this->normalizeContext($opportunity, $profile, $skills);
        $transcript = [];
        foreach ($questions as $index => $question) {
            $transcript[] = [
                'question' => trim((string) $question),
                'answer'   => trim((string) ($answers[$index] ?? '')),
            ];
        }

        $tagNames    = array_map(fn($s) => $s['skillName'], $ctx['skills']);
        $tagsLine    = $tagNames ? implode(', ', $tagNames) : 'not specified';
        $description = $ctx['description'] !== '' ? $ctx['description'] : 'No description provided.';

        $instructions = <<<'INST'
You are a strict but supportive hiring manager evaluating a candidate interview.
Score each answer on how well it shows readiness for THIS specific job.
Empty answers = skipped, score 0.

Return ONLY a raw JSON object, no markdown, no text outside JSON:
{"overallScore":72,"readinessVerdict":"Almost Ready","confidence":68,"structure":75,"relevance":73,"summary":"One paragraph assessment tied to the job","strengths":["specific strength"],"missingPoints":["specific gap"],"improvements":["concrete action"],"questionFeedback":[{"question":"q","answer":"a","score":70,"confidence":60,"structure":75,"relevance":80,"feedback":"specific coaching comment"}],"nextStep":"One concrete action to take before applying","closingNote":"Short encouraging remark"}

readinessVerdict must be exactly one of: "Ready to Apply", "Almost Ready", "Needs More Preparation"
Thresholds: 70+ = Ready to Apply, 50-69 = Almost Ready, below 50 = Needs More Preparation
Judge relevance against the job description and skills/tags. Reward concrete examples that match the role.
INST;

        $input = implode("\n\n", [
            'JOB TITLE: ' . $ctx['title'],
            'SKILLS / TAGS: ' . $tagsLine,
            'JOB DESCRIPTION: ' . $description,
            'INTERVIEW TRANSCRIPT: ' . json_encode($transcript, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        $json = $this->requestJson($instructions, $input, 1000);
        if (is_array($json)) {
            return $this->mergeEvaluation($ctx, $questions, $answers, $json);
        }

        return $this->fallbackEvaluation($ctx, $questions, $answers);
    }

    // ── Context normalisation ─────────────────────────────────────────────────

    private function normalizeContext(array $opportunity, array $profile, array $skills): array
    {
        $skillNames = [];
        foreach ($skills as $skill) {
            if (is_object($skill) && method_exists($skill, 'getSkillName')) {
                $name = trim((string) $skill->getSkillName());
            } elseif (is_array($skill)) {
                $name = trim((string) ($skill['skillName'] ?? $skill['name'] ?? ''));
            } else {
                $name = trim((string) $skill);
            }
            if ($name !== '') { $skillNames[] = $name; }
        }

        $requiredSkills = [];
        foreach ((array) ($opportunity['skills'] ?? []) as $skill) {
            if (!is_array($skill)) { continue; }
            $skillName = trim((string) ($skill['skillName'] ?? ''));
            if ($skillName === '') { continue; }
            $requiredSkills[] = [
                'skillName'     => $skillName,
                'requiredLevel' => (int) ($skill['requiredLevel'] ?? 0),
                'isPrimary'     => (bool) ($skill['isPrimary'] ?? false),
            ];
        }

        return [
            'opportunityId' => (int) ($opportunity['opportunityId'] ?? 0),
            'title'         => trim((string) ($opportunity['title'] ?? 'Opportunity')),
            'companyName'   => trim((string) ($opportunity['managerName'] ?? 'CareerStrand')),
            'type'          => trim((string) ($opportunity['type'] ?? 'role')),
            'category'      => trim((string) ($opportunity['category'] ?? 'General')),
            'requiredLevel' => trim((string) ($opportunity['requiredLevel'] ?? 'Open')),
            'description'   => trim((string) ($opportunity['description'] ?? '')),
            'deadline'      => trim((string) ($opportunity['deadline'] ?? '')),
            'skills'        => $requiredSkills,
            'userProfile'   => [
                'level'       => trim((string) ($profile['level'] ?? 'Starter')),
                'careerGoal'  => trim((string) ($profile['careerGoal'] ?? '')),
                'bio'         => trim((string) ($profile['bio'] ?? '')),
                'location'    => trim((string) ($profile['location'] ?? '')),
                'preferences' => trim((string) ($profile['preferences'] ?? '')),
                'skills'      => $skillNames,
            ],
        ];
    }

    // ── HTTP helpers ──────────────────────────────────────────────────────────

    private function requestJson(string $instructions, string $input, int $maxOutputTokens): array|null
    {
        // First attempt: seed the assistant turn with { to force JSON completion
        try {
            $text = $this->requestText($instructions, $input, $maxOutputTokens, true);
            $json = $this->extractJson($text);
            if (is_array($json)) { return $json; }
        } catch (Throwable $e) {
            // Fall through to second attempt
        }

        // Second attempt: plain request without seed (some providers strip the prefix)
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

        // Seed the assistant turn with { to force a JSON-object completion
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
            CURLOPT_POST            => true,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_HTTPHEADER      => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS      => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_TIMEOUT         => 55,
            CURLOPT_CONNECTTIMEOUT  => 12,
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

        // When assistant seeding is used, the model continues after { — restore it
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
        // Direct decode
        $decoded = json_decode($text, true);
        if (is_array($decoded)) { return $decoded; }

        // Strip markdown code blocks (```json ... ``` or ``` ... ```)
        $stripped = preg_replace('/```(?:json)?\s*([\s\S]*?)\s*```/', '$1', $text);
        if ($stripped !== null && $stripped !== $text) {
            $decoded = json_decode(trim($stripped), true);
            if (is_array($decoded)) { return $decoded; }
        }

        // Extract first { ... last } block
        $start = strpos($text, '{');
        $end   = strrpos($text, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $candidate = substr($text, $start, $end - $start + 1);
            $decoded = json_decode($candidate, true);
            if (is_array($decoded)) { return $decoded; }
        }

        return null;
    }

    // ── Merge helpers ─────────────────────────────────────────────────────────

    private function mergeTechnicalQuestions(array $ctx, array $json): array
    {
        $questions  = $this->normalizeTextList($json['questions']  ?? []);
        $focusAreas = $this->normalizeTextList($json['focusAreas'] ?? []);
        $coachNotes = $this->normalizeTextList($json['coachNotes'] ?? []);
        $fallback   = $this->fallbackTechnicalQuestions($ctx);

        if (count($questions)  < 3) { $questions  = $fallback['questions'];  }
        if (count($focusAreas) < 2) { $focusAreas = $fallback['focusAreas']; }
        if (count($coachNotes) < 2) { $coachNotes = $fallback['coachNotes']; }

        return [
            'success'    => true,
            'opportunity' => $ctx,
            'intro'      => "Let's practice for {$ctx['title']}. I've prepared 5 technical questions based on the role requirements and required skills. Answer as if this were a real interview — be specific and give concrete examples.",
            'questions'  => array_slice($questions, 0, 5),
            'focusAreas' => array_slice($focusAreas, 0, 3),
            'coachNotes' => array_slice($coachNotes, 0, 3),
            'source'     => 'ai',
        ];
    }

    private function fallbackTechnicalQuestions(array $ctx): array
    {
        $skill1 = $this->firstSkill($ctx);
        $skill2 = (string) ($ctx['skills'][1]['skillName'] ?? 'a secondary skill for this role');
        $title  = $ctx['title'];
        $type   = $ctx['type'];

        return [
            'success'    => true,
            'opportunity' => $ctx,
            'intro'      => "Let's practice for {$title}. Here are 5 technical questions tailored to this role. Be specific and draw from real experience.",
            'questions'  => [
                "Walk me through your hands-on experience with {$skill1} — what is the most technically challenging problem you solved using it?",
                "Describe a project or feature you built end-to-end that required {$skill1}. What were the key decisions you made and why?",
                "For a {$type} role like {$title}, how would you approach debugging a critical {$skill1} issue in production under time pressure?",
                "Tell me about your practical experience with {$skill2} — what did you build, what went wrong, and what would you do differently now?",
                "Give me a concrete example where your technical skills in this area directly produced a measurable outcome or prevented a failure.",
            ],
            'focusAreas' => [
                "{$skill1} — depth and real experience",
                "Problem-solving approach and technical judgement",
                "Results, outcomes, and impact delivered",
            ],
            'coachNotes' => [
                "Lead with a concrete example, not a general claim.",
                "State the outcome or impact whenever possible.",
                "Connect each answer back to what this specific role needs.",
            ],
            'source' => 'fallback',
        ];
    }

    private function mergeEvaluation(array $ctx, array $questions, array $answers, array $json): array
    {
        $overallScore = $this->clampScore($json['overallScore'] ?? 0);
        $confidence   = $this->clampScore($json['confidence']   ?? 0);
        $structure    = $this->clampScore($json['structure']    ?? 0);
        $relevance    = $this->clampScore($json['relevance']    ?? 0);

        // Compute readiness from score, then let AI override if valid
        [$readinessVerdict, $readinessClass] = $this->computeReadiness($overallScore);
        $aiVerdict = trim((string) ($json['readinessVerdict'] ?? ''));
        $validVerdicts = ['Ready to Apply', 'Almost Ready', 'Needs More Preparation'];
        if (in_array($aiVerdict, $validVerdicts, true)) {
            $readinessVerdict = $aiVerdict;
            [$readinessVerdict, $readinessClass] = $this->computeReadiness(
                $aiVerdict === 'Ready to Apply' ? 75 : ($aiVerdict === 'Almost Ready' ? 60 : 35)
            );
            $readinessVerdict = $aiVerdict; // keep AI text, just fix class
        }

        $questionFeedback = [];
        foreach ((array) ($json['questionFeedback'] ?? []) as $index => $row) {
            if (!is_array($row)) { continue; }
            $questionFeedback[] = [
                'question'   => trim((string) ($row['question']   ?? ($questions[$index] ?? ''))),
                'answer'     => trim((string) ($row['answer']     ?? ($answers[$index]   ?? ''))),
                'score'      => $this->clampScore($row['score']      ?? 0),
                'confidence' => $this->clampScore($row['confidence'] ?? 0),
                'structure'  => $this->clampScore($row['structure']  ?? 0),
                'relevance'  => $this->clampScore($row['relevance']  ?? 0),
                'feedback'   => trim((string) ($row['feedback'] ?? '')),
            ];
        }

        if (empty($questionFeedback)) {
            return $this->fallbackEvaluation($ctx, $questions, $answers);
        }

        $summary     = trim((string) ($json['summary']     ?? ''));
        $nextStep    = trim((string) ($json['nextStep']    ?? ''));
        $closingNote = trim((string) ($json['closingNote'] ?? ''));

        return [
            'success'     => true,
            'opportunity' => $ctx,
            'result'      => [
                'overallScore'     => $overallScore,
                'readinessVerdict' => $readinessVerdict,
                'readinessClass'   => $readinessClass,
                'confidence'       => $confidence,
                'structure'        => $structure,
                'relevance'        => $relevance,
                'summary'          => $summary !== '' ? $summary : 'The interview is complete.',
                'strengths'        => $this->normalizeTextList($json['strengths']     ?? []),
                'missingPoints'    => $this->normalizeTextList($json['missingPoints'] ?? []),
                'improvements'     => $this->normalizeTextList($json['improvements']  ?? []),
                'questionFeedback' => $questionFeedback,
                'nextStep'         => $nextStep    !== '' ? $nextStep    : 'Review the gaps above and rehearse with specific examples before applying.',
                'closingNote'      => $closingNote !== '' ? $closingNote : 'Keep refining your answers.',
            ],
            'source' => 'ai',
        ];
    }

    private function fallbackEvaluation(array $ctx, array $questions, array $answers): array
    {
        $score    = 0;
        $feedback = [];
        foreach ($questions as $index => $question) {
            $answer = trim((string) ($answers[$index] ?? ''));
            if ($answer === '') {
                $feedback[] = [
                    'question'   => trim((string) $question),
                    'answer'     => '',
                    'score'      => 0, 'confidence' => 0, 'structure' => 0, 'relevance' => 0,
                    'feedback'   => 'This question was skipped. Skipping leaves the interviewer with no evidence.',
                ];
                continue;
            }
            $q = 40;
            if (preg_match('/\b(i|my|me|we)\b/i', $answer))                                          { $q += 10; }
            if (preg_match('/\b(example|because|result|led|built|solved|worked|shipped)\b/i', $answer)) { $q += 20; }
            if (strlen($answer) > 120)                                                                 { $q += 10; }
            $score += $q;
            $feedback[] = [
                'question'   => trim((string) $question),
                'answer'     => $answer,
                'score'      => max(0, min(100, $q)),
                'confidence' => max(0, min(100, $q - 5)),
                'structure'  => max(0, min(100, $q - 10)),
                'relevance'  => max(0, min(100, $q - 15)),
                'feedback'   => 'Add one concrete example and connect it directly to the role requirements.',
            ];
        }

        $overall = count($feedback) ? (int) round($score / count($feedback)) : 0;
        [$readinessVerdict, $readinessClass] = $this->computeReadiness($overall);

        return [
            'success'     => true,
            'opportunity' => $ctx,
            'result'      => [
                'overallScore'     => $overall,
                'readinessVerdict' => $readinessVerdict,
                'readinessClass'   => $readinessClass,
                'confidence'       => max(0, min(100, $overall - 5)),
                'structure'        => max(0, min(100, $overall - 10)),
                'relevance'        => max(0, min(100, $overall - 15)),
                'summary'          => 'Your interview showed effort, but answers need clearer technical examples and stronger role-specific detail.',
                'strengths'        => ['You completed all questions.', 'You attempted to engage with the role context.'],
                'missingPoints'    => ['Specific technical examples', 'Measurable outcomes or results', 'Clear relevance to required skills'],
                'improvements'     => [
                    'Use one concrete example per answer.',
                    'Reference the specific skill or technology mentioned in the question.',
                    'Close each answer by connecting it to what this opportunity needs.',
                ],
                'questionFeedback' => $feedback,
                'nextStep'         => 'Rehearse each answer with one concrete story before applying.',
                'closingNote'      => 'Now you know where to focus. Practice the gaps and try again.',
            ],
            'source' => 'fallback',
        ];
    }

    // ── Utility helpers ───────────────────────────────────────────────────────

    private function computeReadiness(int $score): array
    {
        if ($score >= 70) { return ['Ready to Apply',         'verdict-ready']; }
        if ($score >= 50) { return ['Almost Ready',           'verdict-almost']; }
        return                    ['Needs More Preparation',  'verdict-needs-work'];
    }

    private function firstSkill(array $ctx): string
    {
        return (string) ($ctx['skills'][0]['skillName'] ?? 'the main requirements');
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
