<?php

require_once __DIR__ . '/../Controller/ProfileController.php';

class RecommendationEngine
{
    private const LEVEL_ORDER = [
        'starter' => 1,
        'beginner' => 2,
        'intermediate' => 3,
        'advanced' => 4,
    ];

    public static function buildProfileContext(ProfileController $profileController, int $userId): array
    {
        $profile = $profileController->getByUserId($userId);
        $skills = $profileController->getSkillsByUserId($userId);

        $skillEntries = [];
        $skillNames = [];
        $skillLevels = [];
        foreach ($skills as $skill) {
            $skillName = trim((string) ($skill->getSkillName() ?? ''));
            if ($skillName === '') {
                continue;
            }

            $skillLevel = $skill->getSkillLevel();
            $skillEntries[] = [
                'name' => $skillName,
                'level' => $skillLevel !== null ? max(0, min(100, (int) $skillLevel)) : null,
            ];
            $skillNames[] = self::normalizeText($skillName);
            if ($skillLevel !== null) {
                $skillLevels[] = max(0, min(100, (int) $skillLevel));
            }
        }

        $averageSkillLevel = !empty($skillLevels)
            ? (int) round(array_sum($skillLevels) / count($skillLevels))
            : 0;

        return [
            'location' => self::normalizeContextValue((string) ($profile?->getLocation() ?? '')),
            'preferences' => self::normalizeContextValue((string) ($profile?->getPreferences() ?? '')),
            'careerGoal' => self::normalizeContextValue((string) ($profile?->getCareerGoal() ?? '')),
            'bio' => self::normalizeContextValue((string) ($profile?->getBio() ?? '')),
            'level' => trim((string) ($profile?->getLevel() ?? 'Starter')),
            'completionScore' => max(0, min(100, (int) ($profile?->getCompletionScore() ?? 0))),
            'skills' => $skillEntries,
            'skillNames' => $skillNames,
            'skillLevels' => $skillLevels,
            'averageSkillLevel' => $averageSkillLevel,
        ];
    }

    public static function scoreCourse(array $course, array $context): array
    {
        $title = trim((string) ($course['Title'] ?? 'Untitled course'));
        $description = trim((string) ($course['Description'] ?? ''));
        $category = trim((string) ($course['Categorie'] ?? ''));
        $skill = trim((string) ($course['Skill'] ?? ''));
        $difficulty = trim((string) ($course['Difficulty'] ?? ''));
        $blob = self::normalizeText(implode(' ', [$title, $description, $category, $skill, $difficulty]));

        $score = 10;
        $reasons = [];

        $profileSkillNames = array_values(array_filter((array) ($context['skillNames'] ?? [])));
        $profileSkillLevels = array_values(array_filter((array) ($context['skillLevels'] ?? []), static fn($value) => is_numeric($value)));
        $avgSkillLevel = !empty($profileSkillLevels)
            ? (int) round(array_sum($profileSkillLevels) / count($profileSkillLevels))
            : (int) ($context['averageSkillLevel'] ?? 0);
        $profileGoal = self::normalizeText((string) ($context['careerGoal'] ?? ''));
        $profilePreferences = self::normalizeText((string) ($context['preferences'] ?? ''));
        $profileBio = self::normalizeText((string) ($context['bio'] ?? ''));
        $profileLevelRank = self::levelRank((string) ($context['level'] ?? 'starter'));
        $courseLevelRank = self::levelRank($difficulty);
        $courseLevelText = self::normalizeText($difficulty);

        $skillMatches = [];
        foreach ($profileSkillNames as $profileSkill) {
            if ($profileSkill === '' || !str_contains($blob, $profileSkill)) {
                continue;
            }
            $skillMatches[] = $profileSkill;
        }

        if (!empty($skillMatches)) {
            $score += min(30, 10 + count($skillMatches) * 6);
            $reasons[] = 'Matches your current skills: ' . implode(', ', array_slice($skillMatches, 0, 3)) . '.';
        }

        $goalTokens = self::tokenize($profileGoal);
        if (!empty($goalTokens)) {
            $goalMatches = array_values(array_filter($goalTokens, static fn(string $token): bool => strlen($token) >= 3 && str_contains($blob, $token)));
            if (!empty($goalMatches)) {
                $score += min(24, 8 + count($goalMatches) * 4);
                $reasons[] = 'Supports your career goal: ' . (string) ($context['careerGoal'] ?? '') . '.';
            }
        }

        $preferenceTokens = self::tokenize($profilePreferences);
        if (!empty($preferenceTokens)) {
            $preferenceMatches = array_values(array_filter($preferenceTokens, static fn(string $token): bool => strlen($token) >= 3 && str_contains($blob, $token)));
            if (!empty($preferenceMatches)) {
                $score += min(12, 4 + count($preferenceMatches) * 2);
                $reasons[] = 'It lines up with your learning preferences.';
            }
        }

        $bioTokens = self::tokenize($profileBio);
        if (!empty($bioTokens)) {
            $bioMatches = array_values(array_filter($bioTokens, static fn(string $token): bool => strlen($token) >= 4 && str_contains($blob, $token)));
            if (!empty($bioMatches)) {
                $score += min(10, 3 + count($bioMatches) * 2);
            }
        }

        if ($courseLevelRank > 0 && $profileLevelRank > 0) {
            $distance = abs($courseLevelRank - $profileLevelRank);
            if ($distance === 0) {
                $score += 18;
                $reasons[] = 'Perfectly matches your current level.';
            } elseif ($distance === 1) {
                $score += 12;
                $reasons[] = 'Close to your current level, so it should feel manageable.';
            } elseif ($distance === 2) {
                $score += 6;
                $reasons[] = 'A stretch path that still fits your growth stage.';
            } else {
                $score -= 8;
            }
        }

        if ($avgSkillLevel > 0) {
            if ($avgSkillLevel < 40 && $courseLevelText === 'beginner') {
                $score += 12;
                $reasons[] = 'A good starting point for your current skill level.';
            } elseif ($avgSkillLevel >= 40 && $avgSkillLevel < 70 && $courseLevelText === 'intermediate') {
                $score += 12;
                $reasons[] = 'A solid next step for your current progress.';
            } elseif ($avgSkillLevel >= 70 && $courseLevelText === 'advanced') {
                $score += 12;
                $reasons[] = 'A strong advanced path for your current ability.';
            }
        }

        $completionScore = max(0, min(100, (int) ($context['completionScore'] ?? 0)));
        if ($completionScore >= 80 && $courseLevelText === 'advanced') {
            $score += 8;
        } elseif ($completionScore < 50 && $courseLevelText === 'beginner') {
            $score += 8;
        }

        $videoCount = (int) ($course['videoCount'] ?? 0);
        if ($videoCount > 0) {
            $score += min(8, 2 + (int) round($videoCount / 4));
        }

        $score = max(0, min(100, $score));
        if (empty($reasons)) {
            $reasons[] = 'It supports your growth path and current study direction.';
        }

        return [
            'course' => $course,
            'recommendationScore' => $score,
            'recommendationReasons' => array_slice(array_values(array_unique($reasons)), 0, 3),
        ];
    }

    public static function scoreEvent(array $event, array $context): array
    {
        $title = trim((string) ($event['title'] ?? $event['name'] ?? 'Untitled event'));
        $description = trim((string) ($event['description'] ?? ''));
        $type = trim((string) ($event['type'] ?? ''));
        $location = trim((string) ($event['location'] ?? ''));
        $mode = strtolower(trim((string) ($event['eventMode'] ?? $event['mode'] ?? '')));
        $tags = array_values(array_filter(array_map('trim', (array) ($event['tags'] ?? []))));
        $blob = self::normalizeText(implode(' ', [$title, $description, $type, $location, $mode, implode(' ', $tags)]));

        $score = 8;
        $reasons = [];
        $profileSkillNames = array_values(array_filter((array) ($context['skillNames'] ?? [])));
        $profileGoal = self::normalizeText((string) ($context['careerGoal'] ?? ''));
        $profilePreferences = self::normalizeText((string) ($context['preferences'] ?? ''));
        $profileBio = self::normalizeText((string) ($context['bio'] ?? ''));
        $profileLocation = self::normalizeText((string) ($context['location'] ?? ''));
        $profileLevelRank = self::levelRank((string) ($context['level'] ?? 'starter'));
        $completionScore = max(0, min(100, (int) ($context['completionScore'] ?? 0)));
        $averageSkillLevel = max(0, min(100, (int) ($context['averageSkillLevel'] ?? 0)));

        $matchedSkills = [];
        foreach ($profileSkillNames as $skillName) {
            if ($skillName !== '' && str_contains($blob, $skillName)) {
                $matchedSkills[] = $skillName;
            }
        }

        if (!empty($matchedSkills)) {
            $score += min(24, 8 + count($matchedSkills) * 4);
            $reasons[] = 'Matches your skills: ' . implode(', ', array_slice($matchedSkills, 0, 3)) . '.';
        }

        if ($profileGoal !== '') {
            $goalTokens = self::tokenize($profileGoal);
            $goalMatches = array_values(array_filter($goalTokens, static fn(string $token): bool => strlen($token) >= 3 && str_contains($blob, $token)));
            if (!empty($goalMatches)) {
                $score += min(24, 8 + count($goalMatches) * 4);
                $reasons[] = 'Supports your career goal: ' . (string) ($context['careerGoal'] ?? '') . '.';
            }
        }

        if ($profilePreferences !== '') {
            $preferenceTokens = self::tokenize($profilePreferences);
            $preferenceMatches = array_values(array_filter($preferenceTokens, static fn(string $token): bool => strlen($token) >= 3 && str_contains($blob, $token)));
            if (!empty($preferenceMatches)) {
                $score += min(12, 4 + count($preferenceMatches) * 2);
                $reasons[] = 'Fits your learning preferences.';
            }
        }

        if ($profileBio !== '') {
            $bioTokens = self::tokenize($profileBio);
            $bioMatches = array_values(array_filter($bioTokens, static fn(string $token): bool => strlen($token) >= 4 && str_contains($blob, $token)));
            if (!empty($bioMatches)) {
                $score += min(8, 2 + count($bioMatches) * 2);
            }
        }

        if ($profileLocation !== '' && $location !== '') {
            $profileTokens = self::tokenize($profileLocation);
            $locationTokens = self::tokenize($location);
            $locationMatch = !empty(array_intersect($profileTokens, $locationTokens))
                || str_contains($location, $profileLocation)
                || str_contains($profileLocation, $location);
            if ($locationMatch) {
                $score += 18;
                $reasons[] = 'Matches your location: ' . $location . '.';
            }
        } elseif ($mode === 'online') {
            $score += 8;
            $reasons[] = 'Online format keeps it flexible.';
        }

        if ($type !== '') {
            $typeText = self::normalizeText($type);
            if (($completionScore >= 75 && in_array($typeText, ['hackathon', 'bootcamp', 'career event'], true))
                || ($averageSkillLevel >= 70 && in_array($typeText, ['hackathon', 'bootcamp'], true))) {
                $score += 10;
                $reasons[] = 'A strong stretch for your current momentum.';
            } elseif ($completionScore < 45 && in_array($typeText, ['workshop', 'career event'], true)) {
                $score += 8;
                $reasons[] = 'A good on-ramp for your current stage.';
            } elseif ($profileLevelRank > 0 && in_array($typeText, ['workshop', 'bootcamp', 'hackathon'], true)) {
                $score += 4;
            }
        }

        if ($mode === 'online' && $profileLocation === '') {
            $score += 4;
        }

        if (!empty($tags)) {
            $tagMatches = array_filter($tags, static fn(string $tag): bool => $tag !== '' && str_contains($blob, self::normalizeText($tag)));
            if (!empty($tagMatches)) {
                $score += min(10, 3 + count($tagMatches) * 2);
            }
        }

        if ($score > 100) {
            $score = 100;
        }

        if (empty($reasons)) {
            $reasons[] = 'It fits the direction of your profile.';
        }

        return [
            'event' => $event,
            'recommendationScore' => $score,
            'recommendationReasons' => array_slice(array_values(array_unique($reasons)), 0, 3),
        ];
    }

    public static function scoreOpportunity(array $opportunity, array $context): array
    {
        $title = trim((string) ($opportunity['title'] ?? $opportunity['name'] ?? 'Untitled opportunity'));
        $description = trim((string) ($opportunity['description'] ?? $opportunity['summary'] ?? ''));
        $category = trim((string) ($opportunity['category'] ?? ''));
        $type = trim((string) ($opportunity['type'] ?? ''));
        $location = trim((string) ($opportunity['location'] ?? ''));
        $requiredLevel = trim((string) ($opportunity['requiredLevel'] ?? ''));
        $workMode = strtolower(trim((string) ($opportunity['workMode'] ?? '')));
        $skills = array_values(array_filter(array_map(static fn($skill): string => trim((string) (is_array($skill) ? ($skill['skillName'] ?? $skill['name'] ?? '') : $skill)), (array) ($opportunity['skillDetails'] ?? $opportunity['skills'] ?? []))));
        $blob = self::normalizeText(implode(' ', [$title, $description, $category, $type, $location, $requiredLevel, $workMode, implode(' ', $skills)]));

        $score = 10;
        $reasons = [];
        $skillLookup = [];
        foreach ((array) ($context['skills'] ?? []) as $skill) {
            if (!is_array($skill)) {
                continue;
            }

            $skillName = self::normalizeText((string) ($skill['name'] ?? ''));
            if ($skillName === '') {
                continue;
            }
            $skillLookup[$skillName] = max(0, min(100, (int) ($skill['level'] ?? 0)));
        }

        $matchedSkills = [];
        $skillGaps = [];
        foreach ($skills as $skillName) {
            $normalized = self::normalizeText($skillName);
            if ($normalized === '') {
                continue;
            }

            if (isset($skillLookup[$normalized])) {
                $matchedSkills[] = $skillName;
                if ($requiredLevel !== '' && is_numeric($requiredLevel)) {
                    $gap = max(0, (int) $requiredLevel - (int) $skillLookup[$normalized]);
                    if ($gap > 0) {
                        $skillGaps[] = [$skillName, $gap];
                    }
                }
            }
        }

        if (!empty($matchedSkills)) {
            $score += min(32, 10 + count($matchedSkills) * 6);
            $reasons[] = 'Matched skills: ' . implode(', ', array_slice($matchedSkills, 0, 3)) . '.';
        }

        $profileGoal = self::normalizeText((string) ($context['careerGoal'] ?? ''));
        if ($profileGoal !== '') {
            $goalTokens = self::tokenize($profileGoal);
            $goalMatches = array_values(array_filter($goalTokens, static fn(string $token): bool => strlen($token) >= 3 && str_contains($blob, $token)));
            if (!empty($goalMatches)) {
                $score += min(24, 8 + count($goalMatches) * 4);
                $reasons[] = 'Aligned with your career goal.';
            }
        }

        $profileLocation = self::normalizeText((string) ($context['location'] ?? ''));
        if ($profileLocation !== '' && $location !== '') {
            $profileTokens = self::tokenize($profileLocation);
            $locationTokens = self::tokenize($location);
            $locationMatch = !empty(array_intersect($profileTokens, $locationTokens))
                || str_contains($location, $profileLocation)
                || str_contains($profileLocation, $location);
            if ($locationMatch) {
                $score += 14;
                $reasons[] = 'Matches your location or nearby market.';
            }
        } elseif ($workMode === 'remote') {
            $score += 6;
            $reasons[] = 'Remote format keeps it accessible.';
        }

        $profileLevelRank = self::levelRank((string) ($context['level'] ?? 'starter'));
        $requiredLevelRank = self::levelRank($requiredLevel);
        if ($profileLevelRank > 0 && $requiredLevelRank > 0) {
            $distance = abs($profileLevelRank - $requiredLevelRank);
            if ($distance === 0) {
                $score += 14;
                $reasons[] = 'Matches your current level.';
            } elseif ($distance === 1) {
                $score += 10;
                $reasons[] = 'A close fit for your current stage.';
            } elseif ($distance === 2) {
                $score += 5;
            } else {
                $score -= 6;
            }
        }

        $completionScore = max(0, min(100, (int) ($context['completionScore'] ?? 0)));
        $averageSkillLevel = max(0, min(100, (int) ($context['averageSkillLevel'] ?? 0)));
        if ($completionScore >= 75 && $requiredLevelRank >= 3) {
            $score += 8;
        } elseif ($completionScore < 45 && $requiredLevelRank <= 2 && $requiredLevelRank > 0) {
            $score += 8;
        }
        if ($averageSkillLevel >= 70 && $requiredLevelRank >= 3) {
            $score += 6;
        } elseif ($averageSkillLevel < 40 && $requiredLevelRank <= 2 && $requiredLevelRank > 0) {
            $score += 6;
        }

        if (in_array(strtolower((string) ($opportunity['status'] ?? '')), ['open', 'active', 'published'], true)) {
            $score += 4;
        }

        if ($requiredLevel !== '' && is_numeric($requiredLevel) && empty($skillGaps) && empty($matchedSkills)) {
            $score += 2;
        }

        if (!empty($skillGaps)) {
            $reasons[] = 'Main gap: ' . $skillGaps[0][0] . ' (+'. $skillGaps[0][1] . ').';
        }

        if ($score > 100) {
            $score = 100;
        }
        if ($score < 0) {
            $score = 0;
        }

        if (empty($reasons)) {
            $reasons[] = 'It aligns with your profile and target direction.';
        }

        return [
            'opportunity' => $opportunity,
            'recommendationScore' => $score,
            'recommendationReasons' => array_slice(array_values(array_unique($reasons)), 0, 3),
        ];
    }

    public static function scoreChallenge(array $challenge, array $context): array
    {
        $title = trim((string) ($challenge['title'] ?? 'Untitled challenge'));
        $description = trim((string) ($challenge['description'] ?? ''));
        $hubCategory = trim((string) ($challenge['hubCategory'] ?? $challenge['category'] ?? ''));
        $hubName = trim((string) ($challenge['hubName'] ?? ''));
        $difficulty = trim((string) ($challenge['difficulty'] ?? ''));
        $challengeSkillFocus = trim((string) ($challenge['skillFocus'] ?? ''));
        $hubSkillFocus = trim((string) ($challenge['hubSkillFocus'] ?? ''));
        $blob = self::normalizeText(implode(' ', [$hubCategory, $hubName, $title, $description, $challengeSkillFocus, $hubSkillFocus, $difficulty]));

        $score = 10;
        $reasons = [];
        $profileSkillNames = array_values(array_filter((array) ($context['skillNames'] ?? [])));
        $profileGoal = self::normalizeText((string) ($context['careerGoal'] ?? ''));
        $profileLevelRank = self::levelRank((string) ($context['level'] ?? 'starter'));
        $completionScore = max(0, min(100, (int) ($context['completionScore'] ?? 0)));
        $averageSkillLevel = max(0, min(100, (int) ($context['averageSkillLevel'] ?? 0)));

        if (isset($challenge['joinedHub']) && $challenge['joinedHub']) {
            $score += 18;
            $reasons[] = 'You already belong to this hub.';
        }

        $matchedSkills = [];
        foreach ($profileSkillNames as $skillName) {
            if ($skillName !== '' && str_contains($blob, $skillName)) {
                $matchedSkills[] = $skillName;
            }
        }
        if (!empty($matchedSkills)) {
            $score += min(26, 10 + count($matchedSkills) * 4);
            $reasons[] = 'It lines up with your saved skills.';
        }

        if ($profileGoal !== '') {
            $goalTokens = self::tokenize($profileGoal);
            $goalMatches = array_values(array_filter($goalTokens, static fn(string $token): bool => strlen($token) >= 3 && str_contains($blob, $token)));
            if (!empty($goalMatches)) {
                $score += min(22, 8 + count($goalMatches) * 4);
                $reasons[] = 'Supports your career goal.';
            }
        }

        $difficultyRank = self::levelRank($difficulty);
        if ($difficultyRank > 0 && $profileLevelRank > 0) {
            $distance = abs($difficultyRank - $profileLevelRank);
            if ($distance === 0) {
                $score += 14;
                $reasons[] = 'Matches your current level.';
            } elseif ($distance === 1) {
                $score += 10;
                $reasons[] = 'A natural next step for your growth.';
            } elseif ($distance === 2) {
                $score += 6;
            } else {
                $score -= 6;
            }
        }

        if ($averageSkillLevel >= 70 && $difficultyRank >= 3) {
            $score += 8;
        } elseif ($averageSkillLevel < 40 && $difficultyRank <= 2 && $difficultyRank > 0) {
            $score += 8;
        }
        if ($completionScore >= 75 && $difficultyRank >= 3) {
            $score += 4;
        } elseif ($completionScore < 45 && $difficultyRank <= 2 && $difficultyRank > 0) {
            $score += 4;
        }

        $deadlineRaw = trim((string) ($challenge['deadline'] ?? ''));
        if ($deadlineRaw !== '') {
            $deadline = strtotime($deadlineRaw);
            if ($deadline !== false) {
                $daysUntil = (int) floor(($deadline - time()) / 86400);
                if ($daysUntil >= 0 && $daysUntil <= 14) {
                    $score += 8;
                    $reasons[] = 'The deadline is close enough to act on now.';
                } elseif ($daysUntil > 14) {
                    $score += 4;
                }
            }
        }

        if ($score > 100) {
            $score = 100;
        }
        if ($score < 0) {
            $score = 0;
        }

        if (empty($reasons)) {
            $reasons[] = 'It fits your current hub and skill direction.';
        }

        return [
            'challenge' => $challenge,
            'recommendationScore' => $score,
            'recommendationReasons' => array_slice(array_values(array_unique($reasons)), 0, 3),
        ];
    }

    public static function rankCourses(array $courses, array $context, int $limit = 3): array
    {
        $ranked = [];
        foreach ($courses as $course) {
            $ranked[] = self::scoreCourse($course, $context);
        }

        usort($ranked, static function (array $left, array $right): int {
            $scoreDiff = (int) ($right['recommendationScore'] ?? 0) <=> (int) ($left['recommendationScore'] ?? 0);
            if ($scoreDiff !== 0) {
                return $scoreDiff;
            }

            return (int) ($right['course']['CourseID'] ?? 0) <=> (int) ($left['course']['CourseID'] ?? 0);
        });

        return array_slice($ranked, 0, max(1, $limit));
    }

    private static function normalizeContextValue(?string $value): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return '';
        }

        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $labels = self::extractStructuredLabels($decoded);
            if (!empty($labels)) {
                return implode(', ', array_slice(array_values(array_unique($labels)), 0, 5));
            }
        }

        return trim(preg_replace('/\s+/', ' ', $raw) ?? $raw);
    }

    private static function extractStructuredLabels($value): array
    {
        if (is_string($value)) {
            $label = trim($value);
            return $label !== '' ? [$label] : [];
        }

        if (!is_array($value)) {
            return [];
        }

        $labels = [];
        foreach (['name', 'skillName', 'title', 'label', 'value'] as $key) {
            $candidate = trim((string) ($value[$key] ?? ''));
            if ($candidate !== '' && !is_numeric($candidate)) {
                $labels[] = $candidate;
            }
        }

        foreach ($value as $child) {
            foreach (self::extractStructuredLabels($child) as $label) {
                $labels[] = $label;
            }
        }

        return $labels;
    }

    public static function normalizeText(?string $value): string
    {
        $value = strtolower(trim((string) $value));
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = preg_replace('/[^a-z0-9\s\/\+\-]+/', ' ', $value) ?? $value;
        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    public static function tokenize(?string $value): array
    {
        $value = self::normalizeText($value);
        if ($value === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', preg_split('/[^a-z0-9]+/', $value) ?: [])));
    }

    public static function levelRank(string $level): int
    {
        $key = self::normalizeText($level);
        return self::LEVEL_ORDER[$key] ?? 0;
    }

    public static function scoreByType(array $item, array $context, string $type): array
    {
        return match (strtolower($type)) {
            'event' => self::scoreEvent($item, $context),
            'opportunity' => self::scoreOpportunity($item, $context),
            'challenge' => self::scoreChallenge($item, $context),
            default => $item,
        };
    }
}
