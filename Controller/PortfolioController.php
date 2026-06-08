<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/ProfileController.php';

class PortfolioController
{
    private PDO $pdo;
    private ProfileController $profileController;

    public function __construct()
    {
        $this->pdo = config::getConnexion();
        $this->profileController = new ProfileController();
    }

    public function getPortfolioData(int $userId): array
    {
        $user = $this->getUserRow($userId);
        $profile = $this->profileController->getByUserId($userId);
        $skills = $this->profileController->getSkillsByUserId($userId);
        $theme = ProfileController::normalizeTheme((string) ($profile?->getPortfolioTheme() ?? 'midnight'));

        $courses = $this->getCourseProgress($userId);
        $events = $this->getEventParticipation($userId);
        $applications = $this->getApplications($userId);
        $submissions = $this->getSubmissions($userId);
        $hubCount = $this->getHubMembershipCount($userId);

        $skillCount = count($skills);
        $courseAverage = $this->averageValue($courses, 'progress');
        $courseCompleted = count(array_filter($courses, static fn(array $row): bool => (int) ($row['progress'] ?? 0) >= 100));
        $eventCount = count($events);
        $eventConfirmed = count(array_filter($events, static fn(array $row): bool => strtolower((string) ($row['status'] ?? '')) === 'confirmed'));
        $applicationCount = count($applications);
        $applicationAccepted = count(array_filter($applications, static fn(array $row): bool => strtolower((string) ($row['status'] ?? '')) === 'accepted'));
        $submissionCount = count($submissions);
        $profileScore = (int) ($profile?->getCompletionScore() ?? 0);

        $achievements = $this->buildAchievements([
            'profile' => $profile,
            'skillCount' => $skillCount,
            'courseAverage' => $courseAverage,
            'courseCompleted' => $courseCompleted,
            'eventCount' => $eventCount,
            'eventConfirmed' => $eventConfirmed,
            'applicationCount' => $applicationCount,
            'applicationAccepted' => $applicationAccepted,
            'submissionCount' => $submissionCount,
            'profileScore' => $profileScore,
            'hubCount' => $hubCount,
        ]);
        $certificates = $this->buildCertificates($courses, $user, $profile);

        return [
            'user' => $user,
            'profile' => $profile,
            'skills' => $skills,
            'theme' => $theme,
            'themeLabel' => ProfileController::themeLabel($theme),
            'themeDescription' => ProfileController::themeDescription($theme),
            'courses' => $courses,
            'events' => $events,
            'applications' => $applications,
            'submissions' => $submissions,
            'certificates' => $certificates,
            'stats' => [
                'profileScore' => $profileScore,
                'skillCount' => $skillCount,
                'courseCount' => count($courses),
                'courseAverage' => $courseAverage,
                'courseCompleted' => $courseCompleted,
                'eventCount' => $eventCount,
                'eventConfirmed' => $eventConfirmed,
                'applicationCount' => $applicationCount,
                'applicationAccepted' => $applicationAccepted,
                'submissionCount' => $submissionCount,
                'hubCount' => $hubCount,
            ],
            'achievements' => $achievements,
        ];
    }

    private function getUserRow(int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT userId, fullName, email, role, status, createdAt
             FROM Users
             WHERE userId = :userId
             LIMIT 1'
        );
        $stmt->execute(['userId' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function getCourseProgress(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT
                c.calendarId,
                c.courseId,
                c.startDate,
                c.endDate,
                c.progress,
                c.status,
                COALESCE(co.title, "") AS title,
                COALESCE(co.category, "") AS category,
                COALESCE(co.difficulty, "") AS difficulty,
                COALESCE(co.duration, 0) AS duration
             FROM calendar c
             LEFT JOIN course co ON co.courseId = c.courseId
             WHERE c.userId = :userId
             ORDER BY c.startDate DESC, c.calendarId DESC
             LIMIT 8'
        );
        $stmt->execute(['userId' => $userId]);
        $rows = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = [
                'calendarId' => (int) ($row['calendarId'] ?? 0),
                'courseId' => (int) ($row['courseId'] ?? 0),
                'title' => trim((string) ($row['title'] ?? 'Untitled course')),
                'category' => trim((string) ($row['category'] ?? 'Learning')),
                'difficulty' => trim((string) ($row['difficulty'] ?? 'Open')),
                'duration' => (int) ($row['duration'] ?? 0),
                'startDate' => $this->formatDateLabel($row['startDate'] ?? null, 'Not started'),
                'endDate' => $this->formatDateLabel($row['endDate'] ?? null, 'Open ended'),
                'progress' => max(0, min(100, (int) ($row['progress'] ?? 0))),
                'status' => trim((string) ($row['status'] ?? '')),
            ];
        }
        return $rows;
    }

    private function getEventParticipation(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT
                p.participationId,
                p.eventId,
                p.registrationDate,
                p.attendanceStatus,
                p.status,
                p.rating,
                p.feedback,
                COALESCE(e.title, "") AS title,
                COALESCE(e.type, "") AS type,
                COALESCE(e.location, "") AS location,
                e.date AS eventDate
             FROM participation p
             LEFT JOIN event e ON e.eventId = p.eventId
             WHERE p.userId = :userId
             ORDER BY p.registrationDate DESC, p.participationId DESC
             LIMIT 10'
        );
        $stmt->execute(['userId' => $userId]);
        $rows = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $status = trim((string) ($row['status'] ?? $row['attendanceStatus'] ?? ''));
            $rows[] = [
                'participationId' => (int) ($row['participationId'] ?? 0),
                'eventId' => (int) ($row['eventId'] ?? 0),
                'title' => trim((string) ($row['title'] ?? 'Event')),
                'type' => trim((string) ($row['type'] ?? 'Event')),
                'location' => trim((string) ($row['location'] ?? '')),
                'eventDate' => $this->formatDateLabel($row['eventDate'] ?? null, 'Date TBD'),
                'registrationDate' => $this->formatDateLabel($row['registrationDate'] ?? null, 'Recent'),
                'status' => $status !== '' ? $status : 'Pending',
                'rating' => isset($row['rating']) ? (int) $row['rating'] : null,
                'feedback' => trim((string) ($row['feedback'] ?? '')),
            ];
        }
        return $rows;
    }

    private function getApplications(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT
                a.applicationId,
                a.status,
                a.compatibilityScore,
                a.appliedAt,
                a.motivation,
                COALESCE(o.title, "") AS title,
                COALESCE(o.type, "") AS type,
                COALESCE(o.category, "") AS category,
                COALESCE(o.requiredLevel, "") AS requiredLevel
             FROM Application a
             LEFT JOIN Opportunity o ON o.opportunityId = a.opportunityId
             WHERE a.userId = :userId
             ORDER BY a.appliedAt DESC, a.applicationId DESC
             LIMIT 10'
        );
        $stmt->execute(['userId' => $userId]);
        $rows = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = [
                'applicationId' => (int) ($row['applicationId'] ?? 0),
                'title' => trim((string) ($row['title'] ?? 'Opportunity')),
                'type' => trim((string) ($row['type'] ?? 'Opportunity')),
                'category' => trim((string) ($row['category'] ?? '')),
                'requiredLevel' => trim((string) ($row['requiredLevel'] ?? '')),
                'status' => trim((string) ($row['status'] ?? 'applied')),
                'compatibilityScore' => isset($row['compatibilityScore']) ? (int) $row['compatibilityScore'] : null,
                'appliedAt' => $this->formatDateLabel($row['appliedAt'] ?? null, 'Recent'),
                'motivation' => trim((string) ($row['motivation'] ?? '')),
            ];
        }
        return $rows;
    }

    private function getSubmissions(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT
                s.submissionId,
                s.projectLink,
                s.description,
                s.submittedAt,
                s.score,
                s.status,
                COALESCE(c.title, "") AS challengeTitle,
                COALESCE(c.type, "") AS challengeType,
                COALESCE(sh.name, "") AS hubName,
                COALESCE(sh.category, "") AS hubCategory
             FROM Submission s
             INNER JOIN GroupMember gm ON gm.groupMemberId = s.groupMemberId
             LEFT JOIN Challenge c ON c.challengeId = s.challengeId
             LEFT JOIN SkillHub sh ON sh.groupId = c.groupId
             WHERE gm.userId = :userId
             ORDER BY s.submittedAt DESC, s.submissionId DESC
             LIMIT 10'
        );
        $stmt->execute(['userId' => $userId]);
        $rows = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = [
                'submissionId' => (int) ($row['submissionId'] ?? 0),
                'title' => trim((string) ($row['challengeTitle'] ?? 'Challenge')),
                'challengeType' => trim((string) ($row['challengeType'] ?? '')),
                'hubName' => trim((string) ($row['hubName'] ?? 'Skill Hub')),
                'hubCategory' => trim((string) ($row['hubCategory'] ?? '')),
                'projectLink' => trim((string) ($row['projectLink'] ?? '')),
                'description' => trim((string) ($row['description'] ?? '')),
                'submittedAt' => $this->formatDateLabel($row['submittedAt'] ?? null, 'Recent'),
                'score' => isset($row['score']) ? (int) $row['score'] : null,
                'status' => trim((string) ($row['status'] ?? 'submitted')),
            ];
        }
        return $rows;
    }

    private function getHubMembershipCount(int $userId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM GroupMember gm
             WHERE gm.userId = :userId'
        );
        $stmt->execute(['userId' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    private function buildCertificates(array $courses, ?array $user, ?Profile $profile): array
    {
        $displayName = trim((string) ($user['fullName'] ?? ''));
        if ($displayName === '') {
            $displayName = 'CareerStrand learner';
        }

        $certificates = [];
        foreach ($courses as $course) {
            if ((int) ($course['progress'] ?? 0) < 100) {
                continue;
            }

            $issuedAt = trim((string) ($course['endDate'] ?? ''));
            if ($issuedAt === '') {
                $issuedAt = trim((string) ($course['startDate'] ?? ''));
            }
            if ($issuedAt === '') {
                $issuedAt = date('M j, Y');
            }

            $certificates[] = [
                'title' => trim((string) ($course['title'] ?? 'Course certificate')),
                'subtitle' => 'Completion certificate',
                'issuedAt' => $issuedAt,
                'issuer' => 'CareerStrand',
                'recipient' => $displayName,
                'ref' => 'COURSE-' . (int) ($course['courseId'] ?? 0),
                'type' => 'course',
                'status' => 'completed',
            ];
        }

        return $certificates;
    }

    private function buildAchievements(array $stats): array
    {
        $achievements = [];
        $profile = $stats['profile'] ?? null;
        $goal = trim((string) ($profile?->getCareerGoal() ?? ''));
        $profileScore = (int) ($stats['profileScore'] ?? 0);
        $skillCount = (int) ($stats['skillCount'] ?? 0);
        $courseAverage = (int) ($stats['courseAverage'] ?? 0);
        $courseCompleted = (int) ($stats['courseCompleted'] ?? 0);
        $eventConfirmed = (int) ($stats['eventConfirmed'] ?? 0);
        $applicationAccepted = (int) ($stats['applicationAccepted'] ?? 0);
        $submissionCount = (int) ($stats['submissionCount'] ?? 0);
        $hubCount = (int) ($stats['hubCount'] ?? 0);

        if ($goal !== '') {
            $achievements[] = [
                'title' => 'Career goal defined',
                'copy' => $goal,
            ];
        }
        if ($profileScore >= 60) {
            $achievements[] = [
                'title' => 'Strong profile signal',
                'copy' => $profileScore . '% profile completion.',
            ];
        }
        if ($skillCount >= 3) {
            $achievements[] = [
                'title' => 'Skill stack built',
                'copy' => $skillCount . ' validated skills on the profile.',
            ];
        }
        if ($courseAverage >= 40) {
            $achievements[] = [
                'title' => 'Learning momentum',
                'copy' => 'Average course progress is ' . $courseAverage . '%.',
            ];
        }
        if ($courseCompleted > 0) {
            $achievements[] = [
                'title' => 'Course badge',
                'copy' => $courseCompleted . ' course' . ($courseCompleted === 1 ? '' : 's') . ' reached completion.',
            ];
        }
        if ($courseCompleted >= 1) {
            $achievements[] = [
                'title' => 'Beginner path badge',
                'copy' => 'Finished a full beginner learning path.',
            ];
        }
        if ($courseCompleted >= 3) {
            $achievements[] = [
                'title' => 'Learning path badge',
                'copy' => 'Completed multiple courses and built a study streak.',
            ];
        }
        if ($eventConfirmed > 0) {
            $achievements[] = [
                'title' => 'Event badge',
                'copy' => $eventConfirmed . ' confirmed event' . ($eventConfirmed === 1 ? '' : 's') . '.',
            ];
        }
        if ($eventConfirmed >= 2) {
            $achievements[] = [
                'title' => 'Event regular',
                'copy' => 'Attended multiple events and kept momentum going.',
            ];
        }
        if ($hubCount > 0) {
            $achievements[] = [
                'title' => 'Hub badge',
                'copy' => $hubCount . ' hub membership' . ($hubCount === 1 ? '' : 's') . '.',
            ];
        }
        if ($hubCount >= 3) {
            $achievements[] = [
                'title' => 'Top contributor',
                'copy' => 'Active across several hubs and discussions.',
            ];
        }
        if ($submissionCount > 0) {
            $achievements[] = [
                'title' => 'Challenge badge',
                'copy' => $submissionCount . ' challenge submission' . ($submissionCount === 1 ? '' : 's') . '.',
            ];
        }
        if ($submissionCount >= 3) {
            $achievements[] = [
                'title' => 'Challenge series',
                'copy' => 'Completed a multi-step challenge sequence.',
            ];
        }
        if ($applicationAccepted > 0 || (int) ($stats['applicationCount'] ?? 0) >= 5) {
            $achievements[] = [
                'title' => 'Opportunity explorer',
                'copy' => $applicationAccepted > 0
                    ? $applicationAccepted . ' accepted opportunity application' . ($applicationAccepted === 1 ? '' : 's') . '.'
                    : 'Applied broadly and explored multiple opportunities.',
            ];
        }
        if ($applicationAccepted > 0) {
            $achievements[] = [
                'title' => 'Recruiter traction',
                'copy' => $applicationAccepted . ' accepted application' . ($applicationAccepted === 1 ? '' : 's') . '.',
            ];
        }

        return array_slice($achievements, 0, 8);
    }

    private function averageValue(array $rows, string $key): int
    {
        $values = [];
        foreach ($rows as $row) {
            if (isset($row[$key]) && is_numeric($row[$key])) {
                $values[] = (int) $row[$key];
            }
        }
        if (empty($values)) {
            return 0;
        }

        return (int) round(array_sum($values) / count($values));
    }

    private function formatDateLabel(?string $value, string $fallback): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return $fallback;
        }

        try {
            $date = new DateTime($value);
            return $date->format('M j, Y');
        } catch (Throwable $e) {
            return $fallback;
        }
    }
}

?>
