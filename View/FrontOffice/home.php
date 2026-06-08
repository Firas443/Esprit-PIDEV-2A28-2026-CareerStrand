<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../utils/FrontOfficeAuth.php';
require_once __DIR__ . '/../../utils/AiProfileService.php';
require_once __DIR__ . '/../../utils/RecommendationEngine.php';

$frontUser = requireFrontUser();
$activePage = 'home';
$brandSubtitle = 'career command center';
$pdo = config::getConnexion();
$userId = (int) ($frontUser['userId'] ?? 0);
$fullName = trim((string) ($frontUser['fullName'] ?? 'CareerStrand user'));
$firstName = trim(explode(' ', $fullName)[0] ?? 'there');

function homeEscape(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function homeTableExists(PDO $pdo, string $table): bool
{
    try {
        $query = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $query->execute([$table]);
        return (int) $query->fetchColumn() > 0;
    } catch (Throwable $exception) {
        return false;
    }
}

function homeCountRows(PDO $pdo, string $table, string $where = '1 = 1'): int
{
    if (!homeTableExists($pdo, $table)) {
        return 0;
    }

    try {
        return (int) $pdo->query("SELECT COUNT(*) FROM `$table` WHERE $where")->fetchColumn();
    } catch (Throwable $exception) {
        return 0;
    }
}

function homeFetchRows(PDO $pdo, string $sql, array $params = []): array
{
    try {
        $query = $pdo->prepare($sql);
        $query->execute($params);
        return $query->fetchAll();
    } catch (Throwable $exception) {
        return [];
    }
}

function homePercent(int $part, int $total): int
{
    return $total > 0 ? max(0, min(100, (int) round(($part / $total) * 100))) : 0;
}

function homeClampScore(float|int $value): int
{
    return max(0, min(100, (int) round($value)));
}

function homeSectionStatus(int $score): string
{
    if ($score >= 70) {
        return 'strong';
    }

    if ($score >= 45) {
        return 'watch';
    }

    return 'weak';
}

function homeSectionInsight(string $label, int $score): string
{
    if ($score >= 75) {
        return "{$label} is already a reliable signal in your ADN.";
    }

    if ($score >= 45) {
        return "{$label} is building, but it still needs more proof.";
    }

    return "{$label} needs more activity before it can support your career story.";
}

function homeSectionNextStep(string $label, int $score): string
{
    return match ($label) {
        'Profile strength' => $score >= 75
            ? 'Refresh your profile story with recent wins and keep it current.'
            : 'Complete missing profile fields and sharpen your bio and skills.',
        'Learning strength' => $score >= 75
            ? 'Keep your learning path active and finish the next milestone.'
            : 'Resume a course and turn it into a weekly habit.',
        'Community strength' => $score >= 75
            ? 'Stay visible in hubs by helping others and sharing useful posts.'
            : 'Join or rejoin a hub and add one discussion post or comment.',
        'Opportunity readiness' => $score >= 75
            ? 'Keep applying to the best-fit roles and track which ones respond.'
            : 'Tune your profile and apply to a few well-matched opportunities.',
        'Engagement level' => $score >= 75
            ? 'Maintain your platform rhythm so your ADN keeps growing.'
            : 'Build a simple weekly routine across learning, hubs, and proof of work.',
        default => 'Keep building this part of your ADN.',
    };
}

function homeRoadmapStage(int $score): string
{
    if ($score >= 75) {
        return 'advanced';
    }

    if ($score >= 45) {
        return 'intermediate';
    }

    return 'beginner';
}

function homeRoadmapStageLabel(string $stage): string
{
    return match ($stage) {
        'advanced' => 'Advanced roadmap',
        'intermediate' => 'Intermediate roadmap',
        default => 'Beginner roadmap',
    };
}

function homeLevelRank(?string $level): int
{
    return match (strtolower(trim((string) $level))) {
        'beginner' => 1,
        'intermediate' => 2,
        'advanced' => 3,
        default => 0,
    };
}

function homeParseDate(?string $value): ?DateTimeImmutable
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    try {
        return new DateTimeImmutable($value);
    } catch (Throwable $exception) {
        return null;
    }
}

function homeDaysUntil(?string $value): ?int
{
    $date = homeParseDate($value);
    if ($date === null) {
        return null;
    }

    $today = new DateTimeImmutable('today');
    return (int) $today->diff($date)->format('%r%a');
}

function homeNotificationIcon(string $type): string
{
    return match ($type) {
        'deadline' => '⏳',
        'event' => '📅',
        'challenge' => '🧩',
        'match' => '✨',
        'reply' => '💬',
        'hub' => '🛎',
        default => '•',
    };
}

function homeNotificationPriority(string $type, ?int $daysUntil = null, int $bonus = 0): int
{
    $base = match ($type) {
        'deadline' => 1000,
        'event' => 860,
        'challenge' => 840,
        'match' => 620,
        'reply' => 520,
        'hub' => 480,
        default => 400,
    };

    if ($daysUntil !== null) {
        if ($daysUntil < 0) {
            $base += 260;
        } elseif ($daysUntil === 0) {
            $base += 240;
        } elseif ($daysUntil <= 3) {
            $base += 200 - ($daysUntil * 20);
        } elseif ($daysUntil <= 7) {
            $base += 120 - ($daysUntil * 8);
        } elseif ($daysUntil <= 14) {
            $base += 60 - ($daysUntil * 2);
        }
    }

    return $base + $bonus;
}

function homeBuildNotification(
    string $type,
    string $title,
    string $message,
    string $href,
    ?string $dueLabel = null,
    ?int $daysUntil = null,
    int $bonus = 0,
    ?string $tone = null
): array {
    $priority = homeNotificationPriority($type, $daysUntil, $bonus);
    return [
        'type' => $type,
        'icon' => homeNotificationIcon($type),
        'title' => $title,
        'message' => $message,
        'href' => $href,
        'dueLabel' => $dueLabel,
        'daysUntil' => $daysUntil,
        'priority' => $priority,
        'tone' => $tone ?? $type,
    ];
}

$profile = homeFetchRows($pdo, 'SELECT * FROM Profile WHERE userId = :userId LIMIT 1', ['userId' => $userId])[0] ?? null;
$profileScore = max(0, min(100, (int) ($profile['completionScore'] ?? 0)));
$profileLevel = trim((string) ($profile['level'] ?? 'Starter'));
$profileBio = trim((string) ($profile['bio'] ?? 'Keep building your profile so your work, skills, and preferences tell a stronger story.'));

$skills = homeFetchRows(
    $pdo,
    'SELECT skillName, level
     FROM UserSkill
     WHERE userId = :userId
       AND LOWER(COALESCE(approvalStatus, \'approved\')) = \'approved\'
     ORDER BY level DESC, skillName ASC LIMIT 5',
    ['userId' => $userId]
);

$homeRecommendationContext = [
    'location' => trim((string) ($profile['location'] ?? '')),
    'preferences' => trim((string) ($profile['preferences'] ?? '')),
    'careerGoal' => trim((string) ($profile['careerGoal'] ?? '')),
    'bio' => trim((string) ($profile['bio'] ?? '')),
    'level' => $profileLevel,
    'completionScore' => $profileScore,
    'skills' => array_values(array_map(static function (array $skill): array {
        return [
            'name' => trim((string) ($skill['skillName'] ?? '')),
            'level' => (int) ($skill['level'] ?? 0),
        ];
    }, $skills)),
    'skillNames' => array_values(array_filter(array_map(
        static fn(array $skill): string => strtolower(trim((string) ($skill['skillName'] ?? ''))),
        $skills
    ))),
    'skillLevels' => array_values(array_filter(array_map(
        static fn(array $skill): ?int => is_numeric($skill['level'] ?? null) ? (int) $skill['level'] : null,
        $skills
    ), static fn($value) => $value !== null)),
    'averageSkillLevel' => !empty($skills)
        ? (int) round(array_sum(array_map(static fn(array $skill): int => (int) ($skill['level'] ?? 0), $skills)) / max(1, count($skills)))
        : 0,
];

$joinedHubs = homeFetchRows(
    $pdo,
    "SELECT sh.groupId, sh.name, sh.category, gm.joinedAt
     FROM GroupMember gm
     INNER JOIN SkillHub sh ON sh.groupId = gm.groupId
     WHERE gm.userId = :userId
     ORDER BY gm.joinedAt DESC, gm.groupMemberId DESC
     LIMIT 4",
    ['userId' => $userId]
);

$learningPlans = homeFetchRows(
    $pdo,
    "SELECT c.calendarId, c.progress, c.status, co.title, co.category, co.difficulty
     FROM Calendar c
     LEFT JOIN Course co ON co.courseId = c.courseId
     WHERE c.userId = :userId
     ORDER BY c.startDate DESC, c.calendarId DESC
     LIMIT 3",
    ['userId' => $userId]
);

$applications = homeFetchRows(
    $pdo,
    "SELECT a.status, a.compatibilityScore, a.appliedAt, o.title, o.type
     FROM Application a
     LEFT JOIN Opportunity o ON o.opportunityId = a.opportunityId
     WHERE a.userId = :userId
     ORDER BY a.appliedAt DESC, a.applicationId DESC
     LIMIT 3",
    ['userId' => $userId]
);

$recommendedChallenges = homeFetchRows(
    $pdo,
    "SELECT c.challengeId, c.groupId, c.title, c.type, c.difficulty, c.deadline, sh.name AS hubName
     FROM Challenge c
     LEFT JOIN SkillHub sh ON sh.groupId = c.groupId
     WHERE LOWER(COALESCE(c.status, '')) IN ('published', 'active', 'open')
     ORDER BY c.createdAt DESC, c.challengeId DESC
     LIMIT 3"
);

$upcomingEvents = homeFetchRows(
    $pdo,
    "SELECT eventId, title, type, eventMode, date, location
     FROM Event
     WHERE date IS NULL OR date >= CURDATE()
     ORDER BY date IS NULL ASC, date ASC, eventId DESC
     LIMIT 3"
);

$openOpportunities = homeFetchRows(
    $pdo,
    "SELECT opportunityId, title, type, category, requiredLevel, deadline
     FROM Opportunity
     WHERE LOWER(COALESCE(status, '')) IN ('published', 'active', 'open')
     ORDER BY deadline IS NULL ASC, deadline ASC, opportunityId DESC
     LIMIT 3"
);

$learningStats = homeFetchRows(
    $pdo,
    'SELECT progress FROM Calendar WHERE userId = :userId',
    ['userId' => $userId]
);
$applicationStats = homeFetchRows(
    $pdo,
    'SELECT compatibilityScore FROM Application WHERE userId = :userId',
    ['userId' => $userId]
);
$communityPostCount = homeCountRows($pdo, 'Post', 'userId = ' . $userId);
$communityCommentCount = homeCountRows($pdo, 'Comments', 'userId = ' . $userId);

$mySubmissionCount = homeCountRows(
    $pdo,
    'Submission',
    'groupMemberId IN (SELECT groupMemberId FROM GroupMember WHERE userId = ' . $userId . ')'
);
$reviewedSubmissionCount = homeCountRows(
    $pdo,
    'Submission',
    'score IS NOT NULL AND groupMemberId IN (SELECT groupMemberId FROM GroupMember WHERE userId = ' . $userId . ')'
);
$myApplicationCount = homeCountRows($pdo, 'Application', 'userId = ' . $userId);
$myEventCount = homeCountRows($pdo, 'Participation', 'userId = ' . $userId);
$learningCount = count($learningPlans);
$learningProgressAverage = 0;
if (!empty($learningStats)) {
    $progressValues = array_values(array_filter(array_map(static function ($row) {
        return is_numeric($row['progress'] ?? null) ? (float) $row['progress'] : null;
    }, $learningStats), static fn($value) => $value !== null));
    if (!empty($progressValues)) {
        $learningProgressAverage = (int) round(array_sum($progressValues) / count($progressValues));
    }
}

$applicationCompatibilityAverage = 0;
if (!empty($applicationStats)) {
    $compatibilityValues = array_values(array_filter(array_map(static function ($row) {
        return is_numeric($row['compatibilityScore'] ?? null) ? (float) $row['compatibilityScore'] : null;
    }, $applicationStats), static fn($value) => $value !== null));
    if (!empty($compatibilityValues)) {
        $applicationCompatibilityAverage = (int) round(array_sum($compatibilityValues) / count($compatibilityValues));
    }
}

$profileBioPresent = trim($profileBio) !== '';
$profileSkillCount = count($skills);
$profileStrength = homeClampScore(
    ($profileScore * 0.7)
    + (min(5, $profileSkillCount) * 7)
    + ($profileBioPresent ? 6 : 0)
);
$learningStrength = homeClampScore(
    ($learningProgressAverage * 0.7)
    + (min(4, $learningCount) * 8)
);
$communityStrength = homeClampScore(
    (min(5, count($joinedHubs)) * 14)
    + (min(4, $communityPostCount + $communityCommentCount) * 8)
    + (min(4, $mySubmissionCount) * 6)
);
$opportunityReadiness = homeClampScore(
    ($applicationCompatibilityAverage * 0.7)
    + (min(4, $myApplicationCount) * 10)
);
$engagementLevel = homeClampScore(
    min(
        100,
        ($myEventCount * 14)
        + ($mySubmissionCount * 8)
        + ($myApplicationCount * 8)
        + ($learningCount * 6)
        + ($communityPostCount * 5)
        + ($communityCommentCount * 4)
    )
);
$adnOverallScore = homeClampScore(
    ($profileStrength * 0.28)
    + ($learningStrength * 0.18)
    + ($communityStrength * 0.18)
    + ($opportunityReadiness * 0.18)
    + ($engagementLevel * 0.18)
);
$adnPosition = $adnOverallScore >= 75
    ? 'Strong career momentum'
    : ($adnOverallScore >= 45 ? 'Building a balanced ADN' : 'Early-stage ADN');
$adnSummary = $adnOverallScore >= 75
    ? 'You are turning platform activity into real career momentum.'
    : ($adnOverallScore >= 45
        ? 'Your ADN is growing steadily, and the next push will make it more market-ready.'
        : 'Your ADN is still early, but the basics can be strengthened quickly.');
$adnHeadLine = $adnOverallScore >= 75
    ? 'You look ready to turn visible proof into opportunities.'
    : ($adnOverallScore >= 45
        ? 'Your career foundation is forming well, with room to sharpen a few signals.'
        : 'This is a good moment to build your base before you apply harder.');
$adnStrengths = array_values(array_filter([
    $profileStrength >= 70 ? 'Profile strength is a reliable signal.' : null,
    $learningStrength >= 70 ? 'Learning momentum is strong and visible.' : null,
    $communityStrength >= 70 ? 'Community participation is supporting your profile.' : null,
    $opportunityReadiness >= 70 ? 'Opportunity readiness is already competitive.' : null,
    $engagementLevel >= 70 ? 'Your overall activity pattern is consistent.' : null,
])) ?: ['Keep building proof across profile, learning, and activity.'];
$adnWeaknesses = array_values(array_filter([
    $profileStrength <= 45 ? 'Profile strength still needs more polish.' : null,
    $learningStrength <= 45 ? 'Learning progress is still light.' : null,
    $communityStrength <= 45 ? 'Community activity needs more visibility.' : null,
    $opportunityReadiness <= 45 ? 'Opportunity readiness needs more tailoring.' : null,
    $engagementLevel <= 45 ? 'Engagement is still too sparse to tell a full story.' : null,
])) ?: ['No critical weakness is obvious, but lower sections still need attention.'];

$adnSections = [
    'profile_strength' => [
        'key' => 'profile_strength',
        'label' => 'Profile strength',
        'score' => $profileStrength,
        'status' => homeSectionStatus($profileStrength),
        'insight' => homeSectionInsight('Profile strength', $profileStrength),
        'nextStep' => homeSectionNextStep('Profile strength', $profileStrength),
    ],
    'learning_strength' => [
        'key' => 'learning_strength',
        'label' => 'Learning strength',
        'score' => $learningStrength,
        'status' => homeSectionStatus($learningStrength),
        'insight' => homeSectionInsight('Learning strength', $learningStrength),
        'nextStep' => homeSectionNextStep('Learning strength', $learningStrength),
    ],
    'community_strength' => [
        'key' => 'community_strength',
        'label' => 'Community strength',
        'score' => $communityStrength,
        'status' => homeSectionStatus($communityStrength),
        'insight' => homeSectionInsight('Community strength', $communityStrength),
        'nextStep' => homeSectionNextStep('Community strength', $communityStrength),
    ],
    'opportunity_readiness' => [
        'key' => 'opportunity_readiness',
        'label' => 'Opportunity readiness',
        'score' => $opportunityReadiness,
        'status' => homeSectionStatus($opportunityReadiness),
        'insight' => homeSectionInsight('Opportunity readiness', $opportunityReadiness),
        'nextStep' => homeSectionNextStep('Opportunity readiness', $opportunityReadiness),
    ],
    'engagement_level' => [
        'key' => 'engagement_level',
        'label' => 'Engagement level',
        'score' => $engagementLevel,
        'status' => homeSectionStatus($engagementLevel),
        'insight' => homeSectionInsight('Engagement level', $engagementLevel),
        'nextStep' => homeSectionNextStep('Engagement level', $engagementLevel),
    ],
];

$adnAnalysis = [
    'headline' => $adnHeadLine,
    'summary' => $adnSummary,
    'overallScore' => $adnOverallScore,
    'position' => $adnPosition,
    'sections' => $adnSections,
    'strengths' => $adnStrengths,
    'weaknesses' => $adnWeaknesses,
    'nextActions' => [
        $adnSections['profile_strength']['nextStep'],
        $adnSections['learning_strength']['nextStep'],
        $adnSections['community_strength']['nextStep'],
        $adnSections['opportunity_readiness']['nextStep'],
        $adnSections['engagement_level']['nextStep'],
    ],
    'metrics' => [
        'profileScore' => $profileScore,
        'skillsCount' => $profileSkillCount,
        'learningCount' => $learningCount,
        'learningAverage' => $learningProgressAverage,
        'communityHubs' => count($joinedHubs),
        'communityPosts' => $communityPostCount,
        'communityComments' => $communityCommentCount,
        'challengeSubmissions' => $mySubmissionCount,
        'events' => $myEventCount,
        'applications' => $myApplicationCount,
        'applicationAverage' => $applicationCompatibilityAverage,
    ],
];

$analysisCacheKey = hash('sha256', json_encode([
    'metrics' => $adnAnalysis['metrics'],
    'overallScore' => $adnOverallScore,
    'position' => $adnPosition,
    'summary' => $adnSummary,
    'headline' => $adnHeadLine,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
$analysisCache = $_SESSION['adn_analysis_cache'] ?? null;
$analysisIsFresh = is_array($analysisCache)
    && ($analysisCache['key'] ?? '') === $analysisCacheKey
    && (time() - (int) ($analysisCache['updatedAt'] ?? 0) < 900);

if ($analysisIsFresh && is_array($analysisCache['data'] ?? null)) {
    $adnAnalysis = $analysisCache['data'];
} else {
    try {
        $aiService = new AiProfileService();
        $adnAnalysis = $aiService->generateCareerAnalysis([
            'fullName' => $fullName,
            'overallScore' => $adnOverallScore,
            'position' => $adnPosition,
            'summary' => $adnSummary,
            'headline' => $adnHeadLine,
            'sections' => $adnSections,
            'strengths' => $adnStrengths,
            'weaknesses' => $adnWeaknesses,
            'nextActions' => $adnAnalysis['nextActions'],
            'metrics' => $adnAnalysis['metrics'],
        ]);
    } catch (Throwable $exception) {
        // Keep the page fast and resilient even when AI is unavailable.
    }

    $_SESSION['adn_analysis_cache'] = [
        'key' => $analysisCacheKey,
        'updatedAt' => time(),
        'data' => $adnAnalysis,
    ];
}

$rankedLearningPlans = $learningPlans;
usort($rankedLearningPlans, static function (array $left, array $right) use ($homeRecommendationContext): int {
    $leftScore = RecommendationEngine::scoreCourse([
        'Title' => (string) ($left['title'] ?? ''),
        'Description' => (string) ($left['description'] ?? ''),
        'Categorie' => (string) ($left['category'] ?? ''),
        'Skill' => (string) ($left['skill'] ?? ''),
        'Difficulty' => (string) ($left['difficulty'] ?? ''),
        'videoCount' => (int) ($left['videoCount'] ?? 0),
    ], $homeRecommendationContext)['recommendationScore'] ?? 0;
    $rightScore = RecommendationEngine::scoreCourse([
        'Title' => (string) ($right['title'] ?? ''),
        'Description' => (string) ($right['description'] ?? ''),
        'Categorie' => (string) ($right['category'] ?? ''),
        'Skill' => (string) ($right['skill'] ?? ''),
        'Difficulty' => (string) ($right['difficulty'] ?? ''),
        'videoCount' => (int) ($right['videoCount'] ?? 0),
    ], $homeRecommendationContext)['recommendationScore'] ?? 0;
    return $rightScore <=> $leftScore;
});

$rankedChallenges = $recommendedChallenges;
usort($rankedChallenges, static function (array $left, array $right) use ($homeRecommendationContext): int {
    $leftScore = RecommendationEngine::scoreChallenge($left, $homeRecommendationContext)['recommendationScore'] ?? 0;
    $rightScore = RecommendationEngine::scoreChallenge($right, $homeRecommendationContext)['recommendationScore'] ?? 0;
    return $rightScore <=> $leftScore;
});

$rankedOpportunities = $openOpportunities;
usort($rankedOpportunities, static function (array $left, array $right) use ($homeRecommendationContext): int {
    $leftScore = RecommendationEngine::scoreOpportunity($left, $homeRecommendationContext)['recommendationScore'] ?? 0;
    $rightScore = RecommendationEngine::scoreOpportunity($right, $homeRecommendationContext)['recommendationScore'] ?? 0;
    return $rightScore <=> $leftScore;
});

$rankedEvents = $upcomingEvents;
usort($rankedEvents, static function (array $left, array $right) use ($homeRecommendationContext): int {
    $leftScore = RecommendationEngine::scoreEvent($left, $homeRecommendationContext)['recommendationScore'] ?? 0;
    $rightScore = RecommendationEngine::scoreEvent($right, $homeRecommendationContext)['recommendationScore'] ?? 0;
    return $rightScore <=> $leftScore;
});

$featuredLearning = $rankedLearningPlans[0] ?? ($learningPlans[0] ?? null);
$featuredChallenge = $rankedChallenges[0] ?? ($recommendedChallenges[0] ?? null);
$featuredOpportunity = $rankedOpportunities[0] ?? ($openOpportunities[0] ?? null);
$featuredEvent = $rankedEvents[0] ?? ($upcomingEvents[0] ?? null);
$featuredApplication = $applications[0] ?? null;

$smartNotifications = [];
$joinedHubIds = array_values(array_filter(array_map(
    static fn(array $hub): int => (int) ($hub['groupId'] ?? 0),
    $joinedHubs
)));
$userSkillNames = array_values(array_filter(array_map(
    static fn(array $skill): string => strtolower(trim((string) ($skill['skillName'] ?? ''))),
    $skills
)));
$userSkillLookup = array_fill_keys($userSkillNames, true);

$openOpportunitySkillMap = [];
if (!empty($openOpportunities)) {
    $openOpportunityIds = array_values(array_filter(array_map(
        static fn(array $opportunity): int => (int) ($opportunity['opportunityId'] ?? 0),
        $openOpportunities
    )));
    if (!empty($openOpportunityIds)) {
        $placeholders = implode(',', array_fill(0, count($openOpportunityIds), '?'));
        $skillRows = homeFetchRows(
            $pdo,
            "SELECT opportunityId, skillName
             FROM OpportunitySkill
             WHERE opportunityId IN ($placeholders)
             ORDER BY opportunityId ASC, id ASC",
            $openOpportunityIds
        );
        foreach ($skillRows as $row) {
            $oppId = (int) ($row['opportunityId'] ?? 0);
            $skillName = strtolower(trim((string) ($row['skillName'] ?? '')));
            if ($oppId > 0 && $skillName !== '') {
                $openOpportunitySkillMap[$oppId][] = $skillName;
            }
        }
    }

    foreach ($openOpportunities as &$opportunity) {
        $oppId = (int) ($opportunity['opportunityId'] ?? 0);
        $opportunity['skills'] = $openOpportunitySkillMap[$oppId] ?? [];
    }
    unset($opportunity);
}

foreach ($openOpportunities as $opportunity) {
    $deadlineDays = homeDaysUntil($opportunity['deadline'] ?? null);
    $title = trim((string) ($opportunity['title'] ?? 'Opportunity'));
    $type = strtolower(trim((string) ($opportunity['type'] ?? 'match')));
    $href = 'opportunities.php';

    if ($deadlineDays !== null && $deadlineDays <= 14) {
        $deadlineLabel = $deadlineDays < 0
            ? 'Overdue'
            : ($deadlineDays === 0
                ? 'Due today'
                : ($deadlineDays === 1 ? 'Due tomorrow' : "Due in {$deadlineDays} days"));
        $smartNotifications[] = homeBuildNotification(
            'deadline',
            $title,
            "Application deadline {$deadlineLabel}.",
            $href,
            $deadlineLabel,
            $deadlineDays
        );
    }

    $opportunitySkills = array_values(array_filter(array_map(
        static fn(string $skill): string => strtolower(trim($skill)),
        $openOpportunitySkillMap[(int) ($opportunity['opportunityId'] ?? 0)] ?? []
    )));
    $sharedSkills = array_values(array_intersect($userSkillNames, $opportunitySkills));
    $requiredLevel = trim((string) ($opportunity['requiredLevel'] ?? ''));
    $userLevelRank = homeLevelRank($profileLevel);
    $requiredRank = homeLevelRank($requiredLevel);

    if (!empty($sharedSkills) || ($userLevelRank > 0 && $requiredRank > 0 && $userLevelRank >= $requiredRank)) {
        $matchScore = count($sharedSkills) > 0
            ? min(100, 60 + (count($sharedSkills) * 10) + ($profileScore > 0 ? (int) round($profileScore / 10) : 0))
            : min(100, 52 + ($profileScore > 0 ? (int) round($profileScore / 12) : 0));
        $smartNotifications[] = homeBuildNotification(
            'match',
            'New match: ' . $title,
            !empty($sharedSkills)
                ? 'Matches your saved skills: ' . implode(', ', array_slice($sharedSkills, 0, 3)) . '.'
                : 'Looks aligned with your current profile level.',
            $href,
            $requiredLevel !== '' ? $requiredLevel . ' level' : 'New opportunity',
            $deadlineDays,
            $matchScore
        );
    }
}

foreach ($recommendedChallenges as $challenge) {
    $deadlineDays = homeDaysUntil($challenge['deadline'] ?? null);
    if ($deadlineDays === null || $deadlineDays > 14) {
        continue;
    }

    $challengeTitle = trim((string) ($challenge['title'] ?? 'Challenge'));
    $hubName = trim((string) ($challenge['hubName'] ?? 'Skill Hub'));
    $href = 'thread.php?challengeId=' . (int) ($challenge['challengeId'] ?? 0);
    $deadlineLabel = $deadlineDays < 0
        ? 'Overdue'
        : ($deadlineDays === 0 ? 'Due today' : ($deadlineDays === 1 ? 'Due tomorrow' : "Due in {$deadlineDays} days"));

    $smartNotifications[] = homeBuildNotification(
        'challenge',
        $challengeTitle,
        "Challenge in {$hubName} is {$deadlineLabel}.",
        $href,
        $deadlineLabel,
        $deadlineDays
    );
}

foreach ($upcomingEvents as $event) {
    $eventDays = homeDaysUntil($event['date'] ?? null);
    if ($eventDays === null || $eventDays > 7) {
        continue;
    }

    $eventTitle = trim((string) ($event['title'] ?? 'Event'));
    $eventMode = trim((string) ($event['eventMode'] ?? 'Event'));
    $eventLabel = $eventDays === 0
        ? 'Today'
        : ($eventDays === 1 ? 'Tomorrow' : "In {$eventDays} days");

    $smartNotifications[] = homeBuildNotification(
        'event',
        $eventTitle,
        "{$eventMode} reminder {$eventLabel}.",
        'events.php',
        $eventLabel,
        $eventDays
    );
}

$replyRows = [];
if ($userId > 0) {
    $replySql = "SELECT c.createdAt, c.content, c.parentCommentId, p.postId, p.title AS postTitle, p.groupId, sh.name AS hubName, u.fullName AS commenterName, p.userId AS postAuthorId
                 FROM Comments c
                 INNER JOIN Post p ON p.postId = c.postId
                 LEFT JOIN SkillHub sh ON sh.groupId = p.groupId
                 LEFT JOIN Users u ON u.userId = c.userId
                 WHERE c.status = 'active'
                   AND c.parentCommentId IS NOT NULL
                   AND (p.userId = ?";
    $replyParams = [$userId];
    if (!empty($joinedHubIds)) {
        $replyPlaceholders = implode(',', array_fill(0, count($joinedHubIds), '?'));
        $replySql .= " OR p.groupId IN ($replyPlaceholders)";
        $replyParams = array_merge($replyParams, $joinedHubIds);
    }
    $replySql .= ")
                   AND COALESCE(c.userId, 0) <> ?
                 ORDER BY c.createdAt DESC
                 LIMIT 4";
    $replyParams[] = $userId;
    $replyRows = homeFetchRows($pdo, $replySql, $replyParams);
}

foreach ($replyRows as $reply) {
    $postAuthorId = (int) ($reply['postAuthorId'] ?? 0);
    $groupId = (int) ($reply['groupId'] ?? 0);
    $commenterName = trim((string) ($reply['commenterName'] ?? 'Someone'));
    $hubName = trim((string) ($reply['hubName'] ?? 'Forum'));
    $postTitle = trim((string) ($reply['postTitle'] ?? 'Forum post'));
    $replyLabel = $postAuthorId === $userId ? 'Your post got a reply' : 'Reply in ' . $hubName;
    $smartNotifications[] = homeBuildNotification(
        'reply',
        $replyLabel,
        $commenterName . ' replied to "' . $postTitle . '".',
        'forum.php?groupId=' . $groupId . '&postId=' . (int) ($reply['postId'] ?? 0),
        null,
        null,
        0,
        'reply'
    );
}

if (!empty($joinedHubIds)) {
    $hubPlaceholders = implode(',', array_fill(0, count($joinedHubIds), '?'));
    $hubUpdates = homeFetchRows(
        $pdo,
        "SELECT p.postId, p.groupId, p.title, p.createdAt, sh.name AS hubName, u.fullName AS authorName
         FROM Post p
         LEFT JOIN SkillHub sh ON sh.groupId = p.groupId
         LEFT JOIN Users u ON u.userId = p.userId
         WHERE p.status = 'active'
           AND p.groupId IN ($hubPlaceholders)
         ORDER BY p.createdAt DESC
         LIMIT 4",
        $joinedHubIds
    );

    foreach ($hubUpdates as $post) {
        $groupId = (int) ($post['groupId'] ?? 0);
        $hubName = trim((string) ($post['hubName'] ?? 'Joined hub'));
        $authorName = trim((string) ($post['authorName'] ?? 'A member'));
        $title = trim((string) ($post['title'] ?? 'Hub update'));
        $smartNotifications[] = homeBuildNotification(
            'hub',
            'New update in ' . $hubName,
            $authorName . ' posted "' . $title . '".',
            'hub.php?groupId=' . $groupId,
            'New activity',
            null,
            0,
            'hub'
        );
    }
}

$smartNotifications = array_values(array_filter($smartNotifications, static fn(array $item): bool => trim((string) ($item['title'] ?? '')) !== ''));
usort($smartNotifications, static function (array $left, array $right): int {
    $priorityCompare = ($right['priority'] ?? 0) <=> ($left['priority'] ?? 0);
    if ($priorityCompare !== 0) {
        return $priorityCompare;
    }

    $leftDays = $left['daysUntil'];
    $rightDays = $right['daysUntil'];
    if ($leftDays !== null || $rightDays !== null) {
        return ($leftDays ?? 9999) <=> ($rightDays ?? 9999);
    }

    return strcmp((string) ($right['title'] ?? ''), (string) ($left['title'] ?? ''));
});
$smartNotifications = array_slice($smartNotifications, 0, 8);
$homeNotificationCount = count($smartNotifications);
$homeNotifications = $smartNotifications;

$roadmapStage = homeRoadmapStage($adnOverallScore);
$roadmapIntro = match ($roadmapStage) {
    'advanced' => [
        'title' => 'You are in the advanced progression lane.',
        'copy' => 'The roadmap now helps you sharpen proof, stay visible, and apply more strategically while keeping your ADN strong.',
    ],
    'intermediate' => [
        'title' => 'You are in the intermediate progression lane.',
        'copy' => 'The roadmap now helps you turn learning into visible proof and move toward stronger opportunities.',
    ],
    default => [
        'title' => 'You are in the beginner progression lane.',
        'copy' => 'Follow this roadmap in order so your ADN grows from a profile into a real career journey.',
    ],
};

$roadmapStepDefinitions = match ($roadmapStage) {
    'advanced' => [
        ['title' => 'Refine your profile story', 'copy' => 'Keep your bio, skills, and recent proof current so your strongest signal stays visible.', 'href' => 'profile.php', 'complete' => $profileScore >= 80 && $profileSkillCount >= 4, 'focus' => 'Profile tune-up', 'icon' => 'PF'],
        ['title' => 'Keep learning with intention', 'copy' => 'Finish the next course milestone and keep one learning track active.', 'href' => 'course.php', 'complete' => $learningCount > 0 && $learningProgressAverage >= 70, 'focus' => 'Learning proof', 'icon' => 'LC'],
        ['title' => 'Stay active in your hubs', 'copy' => 'Use comments, posts, and challenge discussions to keep your community presence visible.', 'href' => 'skillhub.php', 'complete' => (!empty($joinedHubs) || $communityPostCount > 0 || $communityCommentCount > 0), 'focus' => 'Community presence', 'icon' => 'CM'],
        ['title' => 'Keep shipping challenges', 'copy' => 'Use new submissions to show practical proof and strengthen your ADN signal.', 'href' => 'skillhub.php', 'complete' => $mySubmissionCount > 0, 'focus' => 'Proof of work', 'icon' => 'CH'],
        ['title' => 'Apply strategically', 'copy' => 'Focus on the best-fit opportunities and improve the match score with every application.', 'href' => 'opportunities.php', 'complete' => $myApplicationCount > 0 && $applicationCompatibilityAverage >= 60, 'focus' => 'Opportunity strategy', 'icon' => 'AP'],
    ],
    'intermediate' => [
        ['title' => 'Complete your profile foundation', 'copy' => 'Make sure your profile, skills, and bio clearly describe the direction you want to grow in.', 'href' => 'profile.php', 'complete' => $profileScore >= 70 && $profileSkillCount >= 3, 'focus' => 'Profile foundation', 'icon' => 'PF'],
        ['title' => 'Finish a beginner course', 'copy' => 'Use learning progress to build confidence and turn course work into visible progress.', 'href' => 'course.php', 'complete' => $learningCount > 0 && $learningProgressAverage >= 55, 'focus' => 'Learning milestone', 'icon' => 'LC'],
        ['title' => 'Join and participate in a hub', 'copy' => 'Community activity helps your ADN look active, collaborative, and serious about growth.', 'href' => 'skillhub.php', 'complete' => count($joinedHubs) > 0, 'focus' => 'Community connection', 'icon' => 'CM'],
        ['title' => 'Submit a challenge', 'copy' => 'Challenge submissions prove that you can build, not just learn.', 'href' => 'skillhub.php', 'complete' => $mySubmissionCount > 0, 'focus' => 'Proof of skill', 'icon' => 'CH'],
        ['title' => 'Apply to a fitting opportunity', 'copy' => 'Once your story looks solid, apply to roles that match your direction and level.', 'href' => 'opportunities.php', 'complete' => $myApplicationCount > 0, 'focus' => 'Opportunity step', 'icon' => 'AP'],
    ],
    default => [
        ['title' => 'Complete your profile', 'copy' => 'Start here so the platform can understand your direction and build the rest of your ADN correctly.', 'href' => 'profile.php', 'complete' => $profileScore >= 70 && $profileSkillCount >= 2, 'focus' => 'Profile first', 'icon' => 'PF'],
        ['title' => 'Finish one beginner course', 'copy' => 'Learning gives you the first layer of structure and momentum.', 'href' => 'course.php', 'complete' => $learningCount > 0 && $learningProgressAverage >= 35, 'focus' => 'Learning next', 'icon' => 'LC'],
        ['title' => 'Join a hub', 'copy' => 'Hubs give you community guidance, support, and opportunities to engage.', 'href' => 'skillhub.php', 'complete' => count($joinedHubs) > 0, 'focus' => 'Community step', 'icon' => 'CM'],
        ['title' => 'Submit your first challenge', 'copy' => 'This is where learning becomes visible proof of ability.', 'href' => 'skillhub.php', 'complete' => $mySubmissionCount > 0, 'focus' => 'Proof of work', 'icon' => 'CH'],
        ['title' => 'Apply to an opportunity', 'copy' => 'Once you have proof, start applying to beginner-friendly roles that fit your path.', 'href' => 'opportunities.php', 'complete' => $myApplicationCount > 0, 'focus' => 'Opportunity step', 'icon' => 'AP'],
    ],
};

$roadmapSteps = [];
$roadmapCurrentIndex = null;
foreach ($roadmapStepDefinitions as $index => $definition) {
    $isComplete = !empty($definition['complete']);
    if ($roadmapCurrentIndex === null && !$isComplete) {
        $roadmapCurrentIndex = $index;
    }

    $roadmapSteps[] = [
        'number' => $index + 1,
        'title' => $definition['title'],
        'copy' => $definition['copy'],
        'href' => $definition['href'],
        'state' => $isComplete ? 'done' : 'locked',
        'focus' => $definition['focus'],
        'icon' => $definition['icon'] ?? 'ST',
    ];
}

if ($roadmapCurrentIndex !== null) {
    $roadmapSteps[$roadmapCurrentIndex]['state'] = 'current';
} elseif (!empty($roadmapSteps)) {
    $roadmapCurrentIndex = count($roadmapSteps) - 1;
    $roadmapSteps[$roadmapCurrentIndex]['state'] = 'current';
}

$roadmapProgressCount = count(array_filter($roadmapSteps, static fn(array $step): bool => $step['state'] === 'done'));
$roadmapTotalCount = count($roadmapSteps);
$roadmapNextStep = $roadmapSteps[$roadmapCurrentIndex] ?? ($roadmapSteps[0] ?? null);
$roadmapPercent = $roadmapTotalCount > 0 ? (int) round(($roadmapProgressCount / $roadmapTotalCount) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CareerStrand | Home</title>
    <link rel="stylesheet" href="assets/css/frontoffice.css">
    <link rel="stylesheet" href="assets/css/front-nav.css">
    <link rel="stylesheet" href="assets/css/home.css">
</head>
<body class="home-page">
    <style>
        html, body {
            margin: 0 !important;
            padding: 0 !important;
        }

        body.home-page {
            margin: 0 !important;
            padding: 0 !important;
            padding-top: 84px !important;
        }

        body.home-page .site-header.cs-front-header {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            width: 100% !important;
            margin-top: 0 !important;
        }

        body.home-page .home-main {
            padding-top: 0 !important;
        }
    </style>
    <?php include __DIR__ . '/partials/front-nav.php'; ?>
    <canvas class="webgl-dna"></canvas>
    <div class="dna-blur-overlay"></div>
    <div class="home-ambient" aria-hidden="true">
        <span class="home-ambient-orb home-ambient-orb-a"></span>
        <span class="home-ambient-orb home-ambient-orb-b"></span>
        <span class="home-ambient-orb home-ambient-orb-c"></span>
        <span class="home-ambient-grid"></span>
        <span class="home-ambient-radial"></span>
    </div>
    <div class="home-background" aria-hidden="true">
        <span class="home-background-mesh home-background-mesh-a"></span>
        <span class="home-background-mesh home-background-mesh-b"></span>
        <span class="home-background-mesh home-background-mesh-c"></span>
        <span class="home-background-ring home-background-ring-a"></span>
        <span class="home-background-ring home-background-ring-b"></span>
        <span class="home-background-ring home-background-ring-c"></span>
        <span class="home-background-rail home-background-rail-a"></span>
        <span class="home-background-rail home-background-rail-b"></span>
        <span class="home-background-rail home-background-rail-c"></span>
        <span class="home-background-particles">
            <i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i>
        </span>
        <span class="home-background-beam home-background-beam-a"></span>
        <span class="home-background-beam home-background-beam-b"></span>
        <span class="home-background-beam home-background-beam-c"></span>
    </div>

    

    <main class="home-main">
        <section class="home-hero">
            <div class="container home-hero-grid">
                <div class="home-hero-copy">
                    <div class="home-eyebrow"><span></span> Signed-in workspace</div>
                    <div class="home-hero-kicker">Career command center</div>
                    <h1>Welcome back, <?= homeEscape($firstName); ?>.</h1>
                    <p>Your front-office home brings together your profile, learning, Skill Hub practice, events, and opportunities without changing the public landing page.</p>
                    <div class="home-hero-meta">
                        <article>
                            <strong><?= $adnOverallScore; ?>%</strong>
                            <span>ADN score</span>
                        </article>
                        <article>
                            <strong><?= $learningStrength; ?>%</strong>
                            <span>Learning strength</span>
                        </article>
                        <article>
                            <strong><?= $opportunityReadiness; ?>%</strong>
                            <span>Opportunity readiness</span>
                        </article>
                    </div>
                    <div class="home-hero-actions">
                        <a class="primary-btn" href="profile.php">Open profile</a>
                        <a class="ghost-btn" href="opportunities.php">Find opportunities</a>
                    </div>
                    <div class="home-hero-note home-hero-analysis">
                        <div class="home-hero-analysis-top">
                            <span>AI readout</span>
                            <strong><?= homeEscape($adnAnalysis['position']); ?></strong>
                        </div>
                        <p><?= homeEscape($adnAnalysis['summary']); ?></p>
                        <div class="home-hero-analysis-grid">
                            <article>
                                <span>Strong signals</span>
                                <ul>
                                    <?php foreach (array_slice($adnAnalysis['strengths'], 0, 2) as $strength) { ?>
                                        <li><?= homeEscape($strength); ?></li>
                                    <?php } ?>
                                </ul>
                            </article>
                            <article>
                                <span>Watch list</span>
                                <ul>
                                    <?php foreach (array_slice($adnAnalysis['weaknesses'], 0, 2) as $weakness) { ?>
                                        <li><?= homeEscape($weakness); ?></li>
                                    <?php } ?>
                                </ul>
                            </article>
                        </div>
                        <div class="home-hero-analysis-next">
                            <span>Next move</span>
                            <p><?= homeEscape($adnAnalysis['nextActions'][0] ?? 'Keep building your ADN with one clear weekly habit.'); ?></p>
                        </div>
                    </div>
                </div>

                <aside class="home-readiness-card home-adn-card">
                    <div class="home-card-top">
                        <span>AI-assisted ADN score</span>
                        <strong><?= homeEscape($adnAnalysis['position']); ?></strong>
                    </div>
                    <div class="home-orbit" style="--score: <?= $adnAnalysis['overallScore']; ?>%;">
                        <div>
                            <strong><?= $adnAnalysis['overallScore']; ?></strong>
                            <span>/100</span>
                        </div>
                    </div>
                    <div class="home-analyzer-grid">
                        <?php foreach ($adnAnalysis['sections'] as $section) { ?>
                            <article class="home-analyzer-section home-analyzer-<?= homeEscape($section['status']); ?>">
                                <div class="home-analyzer-section-top">
                                    <span><?= homeEscape($section['label']); ?></span>
                                    <strong><?= (int) $section['score']; ?>%</strong>
                                </div>
                                <div class="home-analyzer-meter"><span style="width: <?= max(5, min(100, (int) $section['score'])); ?>%;"></span></div>
                                <p><?= homeEscape($section['insight']); ?></p>
                            </article>
                        <?php } ?>
                    </div>
                    <div class="home-readiness-foot home-readiness-foot-tight">
                        <strong>AI note</strong>
                        <span>AI explains the pattern. The score still comes from your real profile, learning, community, and opportunity activity.</span>
                    </div>
                </aside>
            </div>
        </section>

        <section class="container home-metrics">
            <article><span>Learning plans</span><strong><?= $learningCount; ?></strong><small>courses in progress</small></article>
            <article><span>Submissions</span><strong><?= $mySubmissionCount; ?></strong><small><?= $reviewedSubmissionCount; ?> reviewed</small></article>
            <article><span>Applications</span><strong><?= $myApplicationCount; ?></strong><small>opportunity attempts</small></article>
            <article><span>Events</span><strong><?= $myEventCount; ?></strong><small>registrations</small></article>
        </section>

        <section class="container home-section home-notification-section">
            <div class="home-section-head">
                <div>
                    <span>Smart notifications</span>
                    <h2>Important alerts, ranked by urgency.</h2>
                </div>
                <div class="home-notification-summary">
                    <strong><?= count($smartNotifications); ?></strong>
                    <span>alerts ready</span>
                </div>
            </div>
            <article class="home-panel home-notification-panel" aria-live="polite">
                <div class="home-notification-intro">
                    <p>Deadlines appear first, then reminders, and finally activity updates so you can act on the most urgent items quickly.</p>
                    <span class="home-notification-rule">Deadlines first</span>
                </div>
                <?php if (empty($smartNotifications)) { ?>
                    <p class="home-empty home-notification-empty">You’re all caught up. New deadlines, reminders, and updates will appear here.</p>
                <?php } else { ?>
                    <div class="home-notification-list">
                        <?php foreach ($smartNotifications as $notification) { ?>
                            <a class="home-notification-item home-notification-<?= homeEscape($notification['tone'] ?? 'match'); ?>" href="<?= homeEscape($notification['href'] ?? '#'); ?>">
                                <div class="home-notification-icon" aria-hidden="true"><?= homeEscape($notification['icon'] ?? '•'); ?></div>
                                <div class="home-notification-copy">
                                    <div class="home-notification-head">
                                        <strong><?= homeEscape($notification['title'] ?? 'Alert'); ?></strong>
                                        <?php if (!empty($notification['dueLabel'])) { ?>
                                            <span><?= homeEscape($notification['dueLabel']); ?></span>
                                        <?php } ?>
                                    </div>
                                    <p><?= homeEscape($notification['message'] ?? ''); ?></p>
                                </div>
                                <div class="home-notification-meta">
                                    <strong>
                                        <?= homeEscape(
                                            (($notification['daysUntil'] ?? null) === null)
                                                ? 'Fresh'
                                                : (((int) $notification['daysUntil'] < 0)
                                                    ? 'Overdue'
                                                    : (((int) $notification['daysUntil'] <= 3) ? 'Urgent' : 'Soon'))
                                        ); ?>
                                    </strong>
                                    <span>Priority <?= (int) ($notification['priority'] ?? 0); ?></span>
                                </div>
                            </a>
                        <?php } ?>
                    </div>
                <?php } ?>
            </article>
        </section>

        <section class="container home-section home-roadmap-section">
            <div class="home-section-head">
                <div>
                    <span>Personal roadmap</span>
                    <h2>A guided progression path for <?= homeEscape($firstName); ?>.</h2>
                </div>
                <a class="home-link" href="<?= homeEscape($roadmapNextStep['href'] ?? 'profile.php'); ?>">Continue roadmap</a>
            </div>
            <article class="home-panel home-roadmap-panel">
                <div class="home-roadmap-intro">
                    <div>
                        <span><?= homeEscape(homeRoadmapStageLabel($roadmapStage)); ?></span>
                        <h3><?= homeEscape($roadmapIntro['title']); ?></h3>
                        <p><?= homeEscape($roadmapIntro['copy']); ?></p>
                        <div class="home-roadmap-resume">
                            <span>Resume from here</span>
                            <strong><?= homeEscape($roadmapNextStep['title'] ?? 'Keep moving forward'); ?></strong>
                        </div>
                        <div class="home-roadmap-progress">
                            <div class="home-roadmap-progress-bar" aria-hidden="true">
                                <span style="width: <?= $roadmapPercent; ?>%;"></span>
                            </div>
                            <div class="home-roadmap-progress-dots" aria-hidden="true">
                                <?php foreach ($roadmapSteps as $step) { ?>
                                    <span class="home-roadmap-dot home-roadmap-dot-<?= homeEscape($step['state']); ?>"></span>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                    <div class="home-roadmap-summary">
                        <strong><?= $roadmapPercent; ?>%</strong>
                        <span><?= $roadmapProgressCount; ?> of <?= $roadmapTotalCount; ?> steps completed</span>
                    </div>
                </div>

                <div class="home-roadmap-track">
                    <?php foreach ($roadmapSteps as $step) { ?>
                        <a class="home-roadmap-step home-roadmap-<?= homeEscape($step['state']); ?>" href="<?= homeEscape($step['href']); ?>">
                            <span class="home-roadmap-step-index"><?= str_pad((string) $step['number'], 2, '0', STR_PAD_LEFT); ?></span>
                            <div class="home-roadmap-step-copy">
                                <div class="home-roadmap-step-head">
                                    <div class="home-roadmap-step-title">
                                        <span class="home-roadmap-step-icon"><?= homeEscape($step['icon']); ?></span>
                                        <h3><?= homeEscape($step['title']); ?></h3>
                                    </div>
                                    <span><?= homeEscape(ucfirst($step['state'])); ?></span>
                                </div>
                                <p><?= homeEscape($step['copy']); ?></p>
                                <small><?= homeEscape($step['focus']); ?></small>
                            </div>
                        </a>
                    <?php } ?>
                </div>
            </article>
        </section>

        <section class="container home-section">
            <div class="home-board-grid">
                <article class="home-panel home-profile-panel">
                    <div class="home-card-top">
                        <span>Profile foundation</span>
                        <strong><?= $profileScore; ?>%</strong>
                    </div>
                    <h3><?= $profile ? homeEscape($profileLevel) . ' profile' : 'Profile setup needed'; ?></h3>
                    <p><?= homeEscape($profileBio); ?></p>
                    <div class="home-progress"><span style="width: <?= max(4, $profileScore); ?>%;"></span></div>
                    <?php if (!empty($skills)) { ?>
                        <div class="home-chip-list">
                            <?php foreach ($skills as $skill) { ?>
                                <span><?= homeEscape($skill['skillName'] ?? 'Skill'); ?> <?= homeEscape((string) ($skill['level'] ?? '')); ?></span>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </article>

                <article class="home-panel">
                    <div class="home-card-top">
                        <span>Joined hubs</span>
                        <strong><?= count($joinedHubs); ?></strong>
                    </div>
                    <div class="home-list">
                        <?php foreach ($joinedHubs as $hub) { ?>
                            <a href="hub.php?groupId=<?= (int) $hub['groupId']; ?>">
                                <strong><?= homeEscape($hub['name'] ?? 'Skill Hub'); ?></strong>
                                <span><?= homeEscape($hub['category'] ?? 'Community'); ?></span>
                            </a>
                        <?php } ?>
                        <?php if (empty($joinedHubs)) { ?>
                            <p class="home-empty">Join a hub to unlock challenges and discussions.</p>
                        <?php } ?>
                    </div>
                </article>
            </div>
        </section>

        <section class="container home-section">
            <div class="home-section-head">
                <div>
                    <span>Live opportunities</span>
                    <h2>Signals from across the platform.</h2>
                </div>
            </div>
            <div class="home-resource-grid">
                <article class="home-panel">
                    <div class="home-card-top"><span>Learning</span><strong><?= count($learningPlans); ?></strong></div>
                    <div class="home-list">
                        <?php foreach ($learningPlans as $plan) { ?>
                            <a href="course.php">
                                <strong><?= homeEscape($plan['title'] ?? 'Course plan'); ?></strong>
                                <span><?= homeEscape(($plan['category'] ?? 'Learning') . ' / ' . ($plan['progress'] ?? 0) . '%'); ?></span>
                            </a>
                        <?php } ?>
                        <?php if (empty($learningPlans)) { ?>
                            <a href="course.php"><strong>Browse courses</strong><span>Start a structured learning path</span></a>
                        <?php } ?>
                    </div>
                    <div class="home-panel-foot">
                        <span>Featured</span>
                        <strong><?= homeEscape($featuredLearning['title'] ?? 'Build a learning path'); ?></strong>
                    </div>
                </article>

                <article class="home-panel">
                    <div class="home-card-top"><span>Challenges</span><strong><?= count($recommendedChallenges); ?></strong></div>
                    <div class="home-list">
                        <?php foreach ($recommendedChallenges as $challenge) { ?>
                            <a href="thread.php?challengeId=<?= (int) $challenge['challengeId']; ?>">
                                <strong><?= homeEscape($challenge['title'] ?? 'Challenge'); ?></strong>
                                <span><?= homeEscape(($challenge['hubName'] ?? 'Skill Hub') . ' / ' . ($challenge['difficulty'] ?? 'Open')); ?></span>
                            </a>
                        <?php } ?>
                        <?php if (empty($recommendedChallenges)) { ?>
                            <p class="home-empty">No published challenges available right now.</p>
                        <?php } ?>
                    </div>
                    <div class="home-panel-foot">
                        <span>Latest hub task</span>
                        <strong><?= homeEscape($featuredChallenge['hubName'] ?? 'Keep exploring hubs'); ?></strong>
                    </div>
                </article>

                <article class="home-panel">
                    <div class="home-card-top"><span>Opportunities</span><strong><?= count($openOpportunities); ?></strong></div>
                    <div class="home-list">
                        <?php foreach ($openOpportunities as $opportunity) { ?>
                            <a href="opportunities.php">
                                <strong><?= homeEscape($opportunity['title'] ?? 'Opportunity'); ?></strong>
                                <span><?= homeEscape(($opportunity['type'] ?? 'Role') . ' / ' . ($opportunity['requiredLevel'] ?? 'Open level')); ?></span>
                            </a>
                        <?php } ?>
                        <?php if (empty($openOpportunities)) { ?>
                            <p class="home-empty">No live opportunities available right now.</p>
                        <?php } ?>
                    </div>
                    <div class="home-panel-foot">
                        <span>Latest role</span>
                        <strong><?= homeEscape($featuredOpportunity['requiredLevel'] ?? 'Open level'); ?></strong>
                    </div>
                </article>

                <article class="home-panel">
                    <div class="home-card-top"><span>Events</span><strong><?= count($upcomingEvents); ?></strong></div>
                    <div class="home-list">
                        <?php foreach ($upcomingEvents as $event) { ?>
                            <a href="events.php">
                                <strong><?= homeEscape($event['title'] ?? 'Event'); ?></strong>
                                <span><?= homeEscape(($event['eventMode'] ?? 'Event') . ' / ' . ($event['date'] ?? 'Date TBA')); ?></span>
                            </a>
                        <?php } ?>
                        <?php if (empty($upcomingEvents)) { ?>
                            <p class="home-empty">No upcoming events available right now.</p>
                        <?php } ?>
                    </div>
                    <div class="home-panel-foot">
                        <span>Upcoming</span>
                        <strong><?= homeEscape($featuredEvent['date'] ?? 'Date TBA'); ?></strong>
                    </div>
                </article>
            </div>
        </section>

        <?php if (!empty($applications)) { ?>
            <section class="container home-section home-last-section">
                <div class="home-section-head">
                    <div>
                        <span>Application activity</span>
                        <h2>Your recent opportunity moves.</h2>
                    </div>
                    <a class="home-link" href="opportunities.php">Open opportunities</a>
                </div>
                <div class="home-application-strip">
                    <?php foreach ($applications as $application) { ?>
                        <article>
                            <span><?= homeEscape($application['status'] ?? 'pending'); ?></span>
                            <strong><?= homeEscape($application['title'] ?? 'Opportunity'); ?></strong>
                            <small><?= homeEscape(($application['compatibilityScore'] ?? 0) . '% compatibility'); ?></small>
                            <div class="home-application-bar"><span style="width: <?= max(6, min(100, (int) ($application['compatibilityScore'] ?? 0))); ?>%;"></span></div>
                        </article>
                    <?php } ?>
                </div>
            </section>
        <?php } ?>
    </main>

    <div class="home-notif-backdrop" id="homeNotifBackdrop" hidden></div>
    <aside class="home-notif-drawer" id="homeNotifDrawer" aria-label="Notifications panel" aria-hidden="true" hidden>
        <div class="home-notif-drawer-head">
            <div>
                <span class="home-notif-kicker">Smart notifications</span>
                <h2>Important alerts</h2>
            </div>
            <button class="home-notif-close" type="button" id="homeNotifClose" aria-label="Close notifications">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                    <path d="M6 6l12 12"></path>
                    <path d="M18 6L6 18"></path>
                </svg>
            </button>
        </div>
        <div class="home-notif-drawer-sub">
            <p>Deadlines are surfaced first, followed by reminders and activity updates.</p>
            <span><?= count($smartNotifications); ?> alerts</span>
        </div>
        <div class="home-notif-list" id="homeNotifList">
            <?php if (empty($smartNotifications)) { ?>
                <div class="home-notif-empty">You’re all caught up. New alerts will appear here.</div>
            <?php } else { ?>
                <?php foreach ($smartNotifications as $notification) { ?>
                    <a class="home-notif-item home-notif-item-<?= homeEscape($notification['tone'] ?? 'match'); ?>" href="<?= homeEscape($notification['href'] ?? '#'); ?>">
                        <div class="home-notif-icon" aria-hidden="true"><?= homeEscape($notification['icon'] ?? '•'); ?></div>
                        <div class="home-notif-copy">
                            <div class="home-notif-top">
                                <strong><?= homeEscape($notification['title'] ?? 'Alert'); ?></strong>
                                <?php if (!empty($notification['dueLabel'])) { ?>
                                    <span><?= homeEscape($notification['dueLabel']); ?></span>
                                <?php } ?>
                            </div>
                            <p><?= homeEscape($notification['message'] ?? ''); ?></p>
                        </div>
                        <div class="home-notif-meta">
                            <strong>
                                <?= homeEscape(
                                    (($notification['daysUntil'] ?? null) === null)
                                        ? 'Fresh'
                                        : (((int) $notification['daysUntil'] < 0)
                                            ? 'Overdue'
                                            : (((int) $notification['daysUntil'] <= 3) ? 'Urgent' : 'Soon'))
                                ); ?>
                            </strong>
                            <span>Priority <?= (int) ($notification['priority'] ?? 0); ?></span>
                        </div>
                    </a>
                <?php } ?>
            <?php } ?>
        </div>
    </aside>

    <script src="https://unpkg.com/three@0.160.0/build/three.min.js"></script>
    <script src="assets/js/home-dna.js"></script>
    <script>
        (() => {
            const button = document.getElementById('homeNotifButton');
            const drawer = document.getElementById('homeNotifDrawer');
            const backdrop = document.getElementById('homeNotifBackdrop');
            const closeBtn = document.getElementById('homeNotifClose');
            if (!button || !drawer || !backdrop || !closeBtn) return;

            const openDrawer = () => {
                drawer.hidden = false;
                backdrop.hidden = false;
                requestAnimationFrame(() => {
                    drawer.classList.add('is-open');
                    backdrop.classList.add('is-open');
                });
                drawer.setAttribute('aria-hidden', 'false');
            };

            const closeDrawer = () => {
                drawer.classList.remove('is-open');
                backdrop.classList.remove('is-open');
                drawer.setAttribute('aria-hidden', 'true');
                window.setTimeout(() => {
                    if (!drawer.classList.contains('is-open')) {
                        drawer.hidden = true;
                        backdrop.hidden = true;
                    }
                }, 220);
            };

            button.addEventListener('click', openDrawer);
            closeBtn.addEventListener('click', closeDrawer);
            backdrop.addEventListener('click', closeDrawer);
            document.addEventListener('keydown', event => {
                if (event.key === 'Escape' && !drawer.hidden) {
                    closeDrawer();
                }
            });
        })();
    </script>
</body>
</html>
