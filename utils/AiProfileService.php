<?php

class AiProfileService
{
    private string $apiUrl = 'https://router.huggingface.co/v1/chat/completions';
    private string $defaultModel = 'google/gemma-2-2b-it:hf-inference';

    private const ANALYSIS_SECTION_KEYS = [
        'profile_strength' => 'Profile strength',
        'learning_strength' => 'Learning strength',
        'community_strength' => 'Community strength',
        'opportunity_readiness' => 'Opportunity readiness',
        'engagement_level' => 'Engagement level',
    ];

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
        $model = getenv('HF_MODEL')
            ?: getenv('HUGGINGFACE_MODEL')
            ?: ($_SERVER['HF_MODEL'] ?? '')
            ?: ($_SERVER['HUGGINGFACE_MODEL'] ?? '');

        if (trim((string) $model) !== '') {
            return trim((string) $model);
        }

        if (class_exists('config')) {
            try {
                $fromConfig = trim(config::getHuggingFaceFeedbackModel());
                if ($fromConfig !== '') {
                    return $fromConfig;
                }
            } catch (Throwable $e) {}
        }

        return $this->defaultModel;
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

        try {
            $text = $this->requestText($instructions, $input, 450);
            $questions = $this->extractQuestions($text);
        } catch (Throwable $e) {
            $questions = $this->fallbackQuestions($profile);
        }

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

    public function generateCareerAnalysis(array $context): array
    {
        $normalized = $this->normalizeCareerContext($context);
        $fallback = $this->fallbackCareerAnalysis($normalized);

        $instructions = implode("\n", [
            'You are CareerStrand\'s ADN career analyzer.',
            'Analyze the user\'s platform activity and return only valid JSON.',
            'Use the provided metrics to explain the career position clearly and honestly.',
            'Do not invent activity that is not present in the data.',
            'Keep every piece of advice practical, short, and specific to the data.',
            'Return JSON with this exact shape:',
            '{',
            '  "headline": "string",',
            '  "summary": "string",',
            '  "overallScore": 0,',
            '  "position": "string",',
            '  "sections": [',
            '    {"key":"profile_strength","label":"Profile strength","score":0,"status":"strong","insight":"string","nextStep":"string"},',
            '    {"key":"learning_strength","label":"Learning strength","score":0,"status":"strong","insight":"string","nextStep":"string"},',
            '    {"key":"community_strength","label":"Community strength","score":0,"status":"strong","insight":"string","nextStep":"string"},',
            '    {"key":"opportunity_readiness","label":"Opportunity readiness","score":0,"status":"strong","insight":"string","nextStep":"string"},',
            '    {"key":"engagement_level","label":"Engagement level","score":0,"status":"strong","insight":"string","nextStep":"string"}',
            '  ],',
            '  "strengths": ["string"],',
            '  "weaknesses": ["string"],',
            '  "nextActions": ["string"]',
            '}',
            'Use status values: strong, watch, weak.',
            'Make the summary feel like a readable AI analysis, not a generic progress report.',
        ]);

        $input = 'Analyze this CareerStrand ADN context: ' .
            json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        try {
            $text = $this->requestText($instructions, $input, 650);
            $analysis = $this->extractCareerAnalysis($text);
            if (is_array($analysis)) {
                return $this->mergeCareerAnalysis($normalized, $analysis, false);
            }
        } catch (Throwable $e) {
            // Fall through to the deterministic analyzer below.
        }

        return $fallback;
    }

    public function generateCareerAssistantReply(array $context, string $message, array $history = []): array
    {
        $normalized = $this->normalizeAssistantContext($context, $history);
        $conversation = $this->formatAssistantConversation($normalized['recentConversation'] ?? [], $message);
        $isCareerQuestion = $this->isCareerQuestion($message);
        $fallback = $isCareerQuestion
            ? $this->fallbackCareerAssistantReply($normalized, $message)
            : $this->fallbackGeneralAssistantReply($normalized, $message);

        $instructions = $isCareerQuestion
            ? implode("\n", [
                'You are CareerStrand\'s AI Assistant and Career Helper.',
                'You are in an ongoing conversation with the user, not a one-off FAQ.',
                'Use the chat history to stay on topic, avoid repeating yourself, and answer follow-ups naturally.',
                'Help with course choices, opportunity fit, profile improvements, motivation letters, and dashboard data.',
                'Be warm, practical, and specific. Sound like a helpful mentor, not a template.',
                'If the user asks for advice that depends on missing data, say what is missing and suggest the next best action inside the platform.',
                'Do not invent achievements, grades, employers, or application results that are not in the context.',
                'Vary your wording. Sometimes answer directly, sometimes explain step by step, and sometimes offer two options.',
                'If the question is broad, ask one short follow-up question at the end.',
                'Return only the answer text with no heading or quotation marks.',
            ])
            : implode("\n", [
                'You are a helpful general-purpose chatbot inside CareerStrand.',
                'You are in an ongoing conversation with the user, not a one-off FAQ.',
                'Use the chat history to stay on topic, avoid repeating yourself, and answer follow-ups naturally.',
                'Answer clearly and simply, like a friendly tutor or support assistant.',
                'For factual questions, give the direct answer first and then a short explanation or example.',
                'If the user asks something broad, keep it concise and offer to explain further.',
                'Do not mention platform profile data unless the user asks for it.',
                'Return only the answer text with no heading or quotation marks.',
            ]);

        $input = $isCareerQuestion
            ? implode("\n\n", [
                'User profile facts:',
                $this->formatAssistantFacts($normalized),
                'Recent conversation:',
                $conversation !== '' ? $conversation : '- No prior chat in this thread yet.',
                'Current user message:',
                $message,
            ])
            : implode("\n\n", [
                'Recent conversation:',
                $conversation !== '' ? $conversation : '- No prior chat in this thread yet.',
                'Current user message:',
                $message,
            ]);

        try {
            $text = $this->requestText($instructions, $input, 320);
            $reply = trim(preg_replace('/[ \t]+/', ' ', trim($text)) ?? trim($text));
            $reply = trim($reply, " \t\n\r\0\x0B\"'");
            if ($reply === '') {
                throw new RuntimeException('AI returned an empty assistant reply.');
            }

            if ($this->looksGenericAssistantReply($reply)) {
                $reply = $fallback;
            }

            return [
                'ok' => true,
                'reply' => $reply,
                'source' => 'ai',
            ];
        } catch (Throwable $e) {
            return [
                'ok' => true,
                'reply' => $fallback,
                'source' => 'fallback',
            ];
        }
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

    private function normalizeCareerContext(array $context): array
    {
        $sections = $context['sections'] ?? [];
        $normalizedSections = [];
        foreach (self::ANALYSIS_SECTION_KEYS as $key => $label) {
            $section = is_array($sections[$key] ?? null) ? $sections[$key] : [];
            $normalizedSections[$key] = [
                'key' => $key,
                'label' => $label,
                'score' => $this->clampScore($section['score'] ?? 0),
                'status' => $this->normalizeStatus($section['status'] ?? ''),
                'insight' => trim((string) ($section['insight'] ?? '')),
                'nextStep' => trim((string) ($section['nextStep'] ?? '')),
            ];
        }

        $normalized = [
            'fullName' => trim((string) ($context['fullName'] ?? 'CareerStrand user')),
            'overallScore' => $this->clampScore($context['overallScore'] ?? 0),
            'position' => trim((string) ($context['position'] ?? '')),
            'summary' => trim((string) ($context['summary'] ?? '')),
            'headline' => trim((string) ($context['headline'] ?? '')),
            'strengths' => array_values(array_filter(array_map('trim', (array) ($context['strengths'] ?? [])))),
            'weaknesses' => array_values(array_filter(array_map('trim', (array) ($context['weaknesses'] ?? [])))),
            'nextActions' => array_values(array_filter(array_map('trim', (array) ($context['nextActions'] ?? [])))),
            'sections' => $normalizedSections,
            'metrics' => is_array($context['metrics'] ?? null) ? $context['metrics'] : [],
        ];

        return $normalized;
    }

    private function fallbackCareerAnalysis(array $context): array
    {
        $sections = $context['sections'];
        $overallScore = $context['overallScore'];

        if ($overallScore >= 75) {
            $position = 'Strong career momentum';
            $headline = 'You look ready to turn visible proof into opportunities.';
            $summary = 'Your ADN shows solid depth across profile, learning, and activity. The main edge now is consistency: keep shipping proof and let the strongest areas support your applications.';
        } elseif ($overallScore >= 45) {
            $position = 'Building a balanced ADN';
            $headline = 'Your career foundation is forming well, with room to sharpen a few signals.';
            $summary = 'You already have useful traction, but the analyzer can see the gaps that are holding the score back. Strengthen the weaker sections while keeping your strongest habits active.';
        } else {
            $position = 'Early-stage ADN';
            $headline = 'This is a good moment to build your base before you apply harder.';
            $summary = 'Your current activity suggests an early-stage career profile. Focus first on profile completion and one structured learning path, then add proofs of work and engagement.';
        }

        $strengths = [];
        $weaknesses = [];
        $nextActions = [];

        foreach ($sections as $section) {
            if ($section['score'] >= 70) {
                $strengths[] = $section['label'] . ': ' . $this->briefSectionInsight($section['label'], $section['score'], true);
            } elseif ($section['score'] <= 45) {
                $weaknesses[] = $section['label'] . ': ' . $this->briefSectionInsight($section['label'], $section['score'], false);
            }
            $nextActions[] = $section['nextStep'] !== '' ? $section['nextStep'] : $this->defaultNextStep($section['label'], $section['score']);
        }

        return $this->mergeCareerAnalysis($context, [
            'headline' => $headline,
            'summary' => $summary,
            'overallScore' => $overallScore,
            'position' => $position,
            'sections' => array_values($sections),
            'strengths' => array_slice($strengths ?: ['Keep accumulating proof across profile, learning, and activity.'], 0, 3),
            'weaknesses' => array_slice($weaknesses ?: ['No major weaknesses detected yet.'], 0, 3),
            'nextActions' => array_slice(array_values(array_unique($nextActions)), 0, 4),
        ], true);
    }

    private function mergeCareerAnalysis(array $context, array $analysis, bool $isFallback): array
    {
        $sections = [];
        foreach (self::ANALYSIS_SECTION_KEYS as $key => $label) {
            $incoming = is_array($analysis['sections'][$key] ?? null)
                ? $analysis['sections'][$key]
                : ($analysis['sections'][array_search($key, array_keys(self::ANALYSIS_SECTION_KEYS), true)] ?? null);

            if (!is_array($incoming)) {
                $incoming = [];
            }

            $sections[] = [
                'key' => $key,
                'label' => $label,
                'score' => $this->clampScore($incoming['score'] ?? ($context['sections'][$key]['score'] ?? 0)),
                'status' => $this->normalizeStatus($incoming['status'] ?? ($context['sections'][$key]['status'] ?? 'watch')),
                'insight' => trim((string) ($incoming['insight'] ?? ($context['sections'][$key]['insight'] ?? $this->defaultInsight($label, $context['sections'][$key]['score'] ?? 0)))),
                'nextStep' => trim((string) ($incoming['nextStep'] ?? ($context['sections'][$key]['nextStep'] ?? $this->defaultNextStep($label, $context['sections'][$key]['score'] ?? 0)))),
            ];
        }

        $overallScore = $this->clampScore($analysis['overallScore'] ?? ($context['overallScore'] ?? 0));
        $headline = trim((string) ($analysis['headline'] ?? ($context['headline'] ?? '')));
        $summary = trim((string) ($analysis['summary'] ?? ($context['summary'] ?? '')));
        $position = trim((string) ($analysis['position'] ?? ($context['position'] ?? '')));

        if ($headline === '') {
            $headline = $this->headlineForScore($overallScore);
        }
        if ($summary === '') {
            $summary = $this->summaryForScore($overallScore);
        }
        if ($position === '') {
            $position = $this->positionForScore($overallScore);
        }

        $strengths = $this->normalizeTextList($analysis['strengths'] ?? ($context['strengths'] ?? []));
        $weaknesses = $this->normalizeTextList($analysis['weaknesses'] ?? ($context['weaknesses'] ?? []));
        $nextActions = $this->normalizeTextList($analysis['nextActions'] ?? ($context['nextActions'] ?? []));

        if (empty($strengths)) {
            $strengths[] = 'Your strongest sections are the ones already above 70, which means the platform is seeing real momentum.';
        }
        if (empty($weaknesses)) {
            $weaknesses[] = 'No critical gap is obvious yet, but lower-scoring sections still need attention to keep the ADN balanced.';
        }
        if (empty($nextActions)) {
            foreach ($sections as $section) {
                $nextActions[] = $section['nextStep'];
            }
        }

        return [
            'headline' => $headline,
            'summary' => $summary,
            'overallScore' => $overallScore,
            'position' => $position,
            'sections' => $sections,
            'strengths' => array_slice(array_values(array_unique($strengths)), 0, 4),
            'weaknesses' => array_slice(array_values(array_unique($weaknesses)), 0, 4),
            'nextActions' => array_slice(array_values(array_unique($nextActions)), 0, 5),
            'source' => $isFallback ? 'fallback' : 'ai',
        ];
    }

    private function extractCareerAnalysis(string $text): ?array
    {
        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{[\s\S]*\}/', $text, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function normalizeAssistantContext(array $context, array $history = []): array
    {
        $messages = [];
        foreach (array_slice($history, -6) as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $role = strtolower(trim((string) ($entry['role'] ?? '')));
            if (!in_array($role, ['user', 'assistant'], true)) {
                continue;
            }

            $content = trim((string) ($entry['content'] ?? ''));
            if ($content === '') {
                continue;
            }

            $messages[] = [
                'role' => $role,
                'content' => function_exists('mb_substr') ? mb_substr($content, 0, 900) : substr($content, 0, 900),
            ];
        }

        $sections = is_array($context['dashboard'] ?? null) ? $context['dashboard'] : [];

        return [
            'fullName' => trim((string) ($context['fullName'] ?? 'CareerStrand user')),
            'role' => trim((string) ($context['role'] ?? 'user')),
            'profile' => [
                'completionScore' => $this->clampScore($context['profile']['completionScore'] ?? 0),
                'level' => trim((string) ($context['profile']['level'] ?? 'Starter')),
                'bio' => trim((string) ($context['profile']['bio'] ?? '')),
                'location' => trim((string) ($context['profile']['location'] ?? '')),
                'preferences' => trim((string) ($context['profile']['preferences'] ?? '')),
            ],
            'skills' => $this->normalizeTextList($context['skills'] ?? []),
            'learning' => $this->normalizeTextList($context['learning'] ?? []),
            'applications' => $this->normalizeTextList($context['applications'] ?? []),
            'opportunities' => $this->normalizeTextList($context['opportunities'] ?? []),
            'hubs' => $this->normalizeTextList($context['hubs'] ?? []),
            'events' => $this->normalizeTextList($context['events'] ?? []),
            'dashboard' => [
                'adnScore' => $this->clampScore($sections['adnScore'] ?? 0),
                'profileStrength' => $this->clampScore($sections['profileStrength'] ?? 0),
                'learningStrength' => $this->clampScore($sections['learningStrength'] ?? 0),
                'communityStrength' => $this->clampScore($sections['communityStrength'] ?? 0),
                'opportunityReadiness' => $this->clampScore($sections['opportunityReadiness'] ?? 0),
                'engagementLevel' => $this->clampScore($sections['engagementLevel'] ?? 0),
            ],
            'recentConversation' => $messages,
        ];
    }

    private function fallbackCareerAssistantReply(array $context, string $message): string
    {
        $message = trim($message);
        $lower = strtolower($message);
        $profileScore = (int) ($context['profile']['completionScore'] ?? 0);
        $learning = $context['learning'];
        $applications = $context['applications'];
        $opportunities = $context['opportunities'];
        $skills = $context['skills'];
        $adnScore = (int) ($context['dashboard']['adnScore'] ?? 0);
        $recentConversation = $context['recentConversation'] ?? [];
        $lastUserMessage = '';
        $lastAssistantMessage = '';
        foreach (array_reverse($recentConversation) as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $content = trim((string) ($entry['content'] ?? ''));
            if ($content === '') {
                continue;
            }
            if ($lastUserMessage === '' && (($entry['role'] ?? '') === 'user')) {
                $lastUserMessage = $content;
            } elseif ($lastAssistantMessage === '' && (($entry['role'] ?? '') === 'assistant')) {
                $lastAssistantMessage = $content;
            }
            if ($lastUserMessage !== '' && $lastAssistantMessage !== '') {
                break;
            }
        }

        $followUp = $this->buildFollowUpQuestion($message, $context);
        $conversationHint = $lastUserMessage !== ''
            ? ' Earlier you were talking about "' . $this->trimForSentence($lastUserMessage, 90) . '".'
            : '';

        if (str_contains($lower, 'motivation') || str_contains($lower, 'cover letter')) {
            return trim(
                "For the motivation letter, keep it personal and specific: why this role, why this company, what you already do well, and what you want to learn next."
                . " Pick one or two skills from your profile and connect them to the job instead of writing a general introduction."
                . " {$followUp}"
            );
        }

        if (str_contains($lower, 'dashboard') || str_contains($lower, 'score') || str_contains($lower, 'data')) {
            return trim(
                "Your dashboard is showing an ADN score of {$adnScore}%, which is the overall picture."
                . " The most useful next check is profile completion at {$profileScore}% and your recent learning or application activity, because those are usually the fastest levers to improve."
                . $conversationHint
                . " {$followUp}"
            );
        }

        if (str_contains($lower, 'opportunity') || str_contains($lower, 'job') || str_contains($lower, 'internship') || str_contains($lower, 'best fit')) {
            $bestOpportunity = $opportunities[0] ?? '';
            $bestApplication = $applications[0] ?? '';
            $applicationHint = !empty($applications)
                ? 'Your recent applications suggest you already have a starting pattern, so I would lean into the roles that are closest to those. '
                : 'You do not have much application history yet, so the safest move is to start with the closest realistic match. ';
            $opportunityText = $bestOpportunity !== ''
                ? ' One option from your current feed is "' . $this->trimForSentence($bestOpportunity, 120) . '".'
                : ' I do not see a live opportunity cached right now.';
            $applicationText = $bestApplication !== ''
                ? ' The strongest previous signal I see is "' . $this->trimForSentence($bestApplication, 120) . '".'
                : '';
            return trim(
                "If you want the best-fit opportunity, I would start with the option that matches your current level and the skills already visible in your profile."
                . ' ' . $applicationHint
                . $opportunityText
                . $applicationText
                . " {$followUp}"
            );
        }

        if (str_contains($lower, 'course') || str_contains($lower, 'learn') || str_contains($lower, 'next step')) {
            $nextLearning = $learning[0] ?? '';
            $secondLearning = $learning[1] ?? '';
            $courseText = $nextLearning !== ''
                ? 'From your current activity, the first course trail to keep moving is "' . $this->trimForSentence($nextLearning, 120) . '".'
                : 'I do not see an active course in the current context, so the best move is to pick one clear learning path and stay with it for a bit.';
            $secondText = $secondLearning !== ''
                ? ' If you want a backup option, "' . $this->trimForSentence($secondLearning, 120) . '" is also worth considering.'
                : '';
            return trim(
                "The best next course is the one that supports the kind of role you want to grow into."
                . ' ' . $courseText
                . $secondText
                . " Keep one learning track active until it turns into visible proof."
                . " {$followUp}"
            );
        }

        if (str_contains($lower, 'profile') || str_contains($lower, 'improve')) {
            $skillHint = !empty($skills)
                ? 'Your strongest visible skills are already available, so the main win is to place them in a clearer story.'
                : 'You should add a few concrete skills that match the direction you want.';
            $bioHint = trim((string) ($context['profile']['bio'] ?? '')) !== ''
                ? 'Your bio exists already, so I would sharpen it instead of rewriting from scratch.'
                : 'You do not seem to have a strong bio yet, so adding one would help immediately.';
            return trim(
                "To improve your profile, focus on one simple story: who you are, what you can do, and what kind of role you want next."
                . " Your profile completion is {$profileScore}%, so the fastest win is usually to tighten the bio and make the skills section feel more intentional."
                . " {$skillHint} {$bioHint}"
                . " {$followUp}"
            );
        }

        $profileLevelText = $context['profile']['level'] !== '' ? 'You are currently at the "' . $context['profile']['level'] . '" level.' : '';
        $recentHint = $lastAssistantMessage !== ''
            ? ' I have also kept in mind the last reply: "' . $this->trimForSentence($lastAssistantMessage, 90) . '".'
            : '';
        return trim(
            "I can help with course choice, opportunity fit, profile edits, motivation letters, or dashboard data."
            . " {$profileLevelText} Right now your profile is at {$profileScore}% completion, so the best next move is usually the area that will raise your opportunity readiness fastest."
            . $recentHint
            . " {$followUp}"
        );
    }

    private function fallbackGeneralAssistantReply(array $context, string $message): string
    {
        $message = trim($message);
        $lower = strtolower($message);

        if ($lower === '' || in_array($lower, ['hi', 'hello', 'hey', 'yo'], true)) {
            return 'Hi. What would you like help with?';
        }

        if (str_contains($lower, 'html')) {
            return 'HTML stands for HyperText Markup Language. It is the standard language used to structure web pages with elements like headings, paragraphs, links, images, and buttons. It tells the browser what each part of the page is.';
        }

        if (str_contains($lower, 'css')) {
            return 'CSS stands for Cascading Style Sheets. It is used to style HTML by controlling layout, colors, spacing, fonts, and how a page looks on different screen sizes.';
        }

        if (str_contains($lower, 'javascript') || str_contains($lower, 'js')) {
            return 'JavaScript is the programming language that makes web pages interactive. It is used for things like button clicks, form validation, live updates, menus, and chat behavior.';
        }

        if (str_contains($lower, 'php')) {
            return 'PHP is a server-side language commonly used to build dynamic websites. It runs on the server, talks to the database, and generates the page or JSON the browser receives.';
        }

        if (str_contains($lower, 'what is') || str_contains($lower, 'explain')) {
            return 'Here is the short version: ' . $this->trimForSentence($message, 160) . '. If you want, I can also give you a simpler explanation or an example.';
        }

        return 'I can help with that. If you want a quick answer, ask it directly and I will keep it simple.';
    }

    private function formatAssistantConversation(array $history, string $currentMessage): string
    {
        $lines = [];
        foreach ($history as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $role = trim((string) ($entry['role'] ?? ''));
            $content = trim((string) ($entry['content'] ?? ''));
            if ($content === '' || !in_array($role, ['user', 'assistant'], true)) {
                continue;
            }
            $label = $role === 'user' ? 'User' : 'Assistant';
            $lines[] = '- ' . $label . ': ' . $this->trimForSentence($content, 220);
        }

        $lines[] = '- User: ' . $this->trimForSentence($currentMessage, 220);

        return implode("\n", $lines);
    }

    private function formatAssistantFacts(array $context): string
    {
        $lines = [];
        $profile = $context['profile'] ?? [];
        $dashboard = $context['dashboard'] ?? [];
        $skills = array_slice((array) ($context['skills'] ?? []), 0, 5);
        $learning = array_slice((array) ($context['learning'] ?? []), 0, 3);
        $applications = array_slice((array) ($context['applications'] ?? []), 0, 3);
        $opportunities = array_slice((array) ($context['opportunities'] ?? []), 0, 3);

        $lines[] = 'Name: ' . ($context['fullName'] ?? 'CareerStrand user');
        $lines[] = 'Role: ' . ($context['role'] ?? 'user');
        $lines[] = 'Profile completion: ' . (int) ($profile['completionScore'] ?? 0) . '%';
        $lines[] = 'Profile level: ' . ($profile['level'] ?? 'Starter');
        $lines[] = 'ADN score: ' . (int) ($dashboard['adnScore'] ?? 0) . '%';

        if (!empty($profile['bio'])) {
            $lines[] = 'Bio: ' . $this->trimForSentence((string) $profile['bio'], 160);
        }
        if (!empty($skills)) {
            $lines[] = 'Skills: ' . implode(', ', array_map(fn($item) => $this->trimForSentence((string) $item, 50), $skills));
        }
        if (!empty($learning)) {
            $lines[] = 'Learning: ' . implode(' | ', array_map(fn($item) => $this->trimForSentence((string) $item, 90), $learning));
        }
        if (!empty($applications)) {
            $lines[] = 'Applications: ' . implode(' | ', array_map(fn($item) => $this->trimForSentence((string) $item, 90), $applications));
        }
        if (!empty($opportunities)) {
            $lines[] = 'Opportunities: ' . implode(' | ', array_map(fn($item) => $this->trimForSentence((string) $item, 90), $opportunities));
        }

        return implode("\n", $lines);
    }

    private function buildFollowUpQuestion(string $message, array $context): string
    {
        $lower = strtolower($message);
        if (str_contains($lower, 'course')) {
            return 'If you want, I can compare two course paths and tell you which one fits your current stage better.';
        }

        if (str_contains($lower, 'opportunity') || str_contains($lower, 'job') || str_contains($lower, 'internship')) {
            return 'If you want, send me the role name and I will help you judge whether it is a strong match.';
        }

        if (str_contains($lower, 'profile')) {
            return 'If you want, I can also rewrite your profile summary in a more career-focused way.';
        }

        if (str_contains($lower, 'motivation')) {
            return 'If you want, I can draft a motivation letter opening paragraph for your next application.';
        }

        if (str_contains($lower, 'dashboard') || str_contains($lower, 'score') || str_contains($lower, 'data')) {
            return 'If you want, I can also translate the other dashboard sections into plain language.';
        }

        $profileScore = (int) ($context['profile']['completionScore'] ?? 0);
        if ($profileScore < 50) {
            return 'If you want, I can help you focus on the one change that would improve your profile fastest.';
        }

        return 'If you want, I can turn this into a simple next-step plan for your week.';
    }

    private function looksGenericAssistantReply(string $reply): bool
    {
        $normalized = strtolower(trim(preg_replace('/\s+/', ' ', $reply) ?? $reply));
        if ($normalized === '') {
            return true;
        }

        $genericMarkers = [
            'i can help with course choice',
            'i can help with course choice, opportunity fit',
            'the safest next course choice is',
            'your dashboard is currently showing an adn score',
            'to improve your profile, fill missing fields',
        ];

        foreach ($genericMarkers as $marker) {
            if (str_contains($normalized, $marker)) {
                return true;
            }
        }

        return false;
    }

    private function trimForSentence(string $value, int $limit): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);
        if ($value === '') {
            return '';
        }

        return function_exists('mb_substr') ? mb_substr($value, 0, $limit) : substr($value, 0, $limit);
    }

    private function normalizeStatus(mixed $status): string
    {
        $status = strtolower(trim((string) $status));
        if (in_array($status, ['strong', 'watch', 'weak'], true)) {
            return $status;
        }

        return 'watch';
    }

    private function clampScore(mixed $value): int
    {
        if (!is_numeric($value)) {
            return 0;
        }

        return max(0, min(100, (int) round((float) $value)));
    }

    private function normalizeTextList(mixed $items): array
    {
        if (!is_array($items)) {
            return [];
        }

        $normalized = [];
        foreach ($items as $item) {
            if (is_string($item) || is_numeric($item)) {
                $text = trim((string) $item);
                if ($text !== '') {
                    $normalized[] = $text;
                }
            }
        }

        return $normalized;
    }

    private function isCareerQuestion(string $message): bool
    {
        $lower = strtolower(trim($message));
        if ($lower === '') {
            return false;
        }

        $keywords = [
            'course',
            'opportunity',
            'job',
            'internship',
            'profile',
            'motivation',
            'dashboard',
            'application',
            'career',
            'study',
            'learn',
            'skill',
            'match',
            'cv',
            'resume',
            'adn',
        ];

        foreach ($keywords as $keyword) {
            if (str_contains($lower, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function defaultInsight(string $label, int $score): string
    {
        if ($score >= 70) {
            return "{$label} is one of your strongest signals right now.";
        }

        if ($score >= 45) {
            return "{$label} is moving in the right direction but still needs more proof.";
        }

        return "{$label} is currently light, so a small push here will make a visible difference.";
    }

    private function defaultNextStep(string $label, int $score): string
    {
        return match ($label) {
            'Profile strength' => $score >= 70
                ? 'Refresh your profile story with recent work, then keep it consistent.'
                : 'Complete missing profile fields and add a stronger bio plus skills.',
            'Learning strength' => $score >= 70
                ? 'Keep one active learning plan moving and finish the next milestone.'
                : 'Start or resume a course and turn it into a regular weekly habit.',
            'Community strength' => $score >= 70
                ? 'Stay visible in hubs by posting or helping others with feedback.'
                : 'Join at least one hub and add one post or comment to start showing presence.',
            'Opportunity readiness' => $score >= 70
                ? 'Keep applying to the best-fit opportunities and track response patterns.'
                : 'Apply to a few targeted opportunities after tightening your profile story.',
            'Engagement level' => $score >= 70
                ? 'Keep your platform rhythm steady so your ADN stays active.'
                : 'Build a simple weekly routine across learning, community, and submissions.',
            default => 'Keep building evidence in this area.',
        };
    }

    private function briefSectionInsight(string $label, int $score, bool $strong): string
    {
        if ($strong) {
            return $label . ' is already supporting your career signal.';
        }

        return $label . ' needs more activity before it can support applications confidently.';
    }

    private function headlineForScore(int $score): string
    {
        if ($score >= 75) {
            return 'You are turning platform activity into real career momentum.';
        }

        if ($score >= 45) {
            return 'Your ADN is growing steadily, and the next push will make it more market-ready.';
        }

        return 'Your ADN is still early, but the basics can be strengthened quickly.';
    }

    private function summaryForScore(int $score): string
    {
        if ($score >= 75) {
            return 'The analyzer sees strong proof across multiple areas, with only small refinements needed to stay competitive.';
        }

        if ($score >= 45) {
            return 'The score shows meaningful progress, but a few weak sections are still holding the overall career picture back.';
        }

        return 'The platform can see some early signals, but the profile still needs more completion, learning, and visible proof.';
    }

    private function positionForScore(int $score): string
    {
        if ($score >= 75) {
            return 'Strong career momentum';
        }

        if ($score >= 45) {
            return 'Building a balanced ADN';
        }

        return 'Early-stage ADN';
    }
}
?>
