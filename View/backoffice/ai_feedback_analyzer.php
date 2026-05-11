<?php
require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json');

function ensureSentimentColumn(PDO $db): void
{
    $sql = "SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'Participation'
              AND COLUMN_NAME = 'sentiment'";
    $exists = (int)$db->query($sql)->fetchColumn() > 0;
    if (!$exists) {
        $db->exec("ALTER TABLE Participation ADD COLUMN sentiment VARCHAR(20) DEFAULT 'Neutral'");
    }
}

function textContainsAny(string $text, array $words): int
{
    $score = 0;
    foreach ($words as $word) {
        if (strpos($text, $word) !== false) {
            $score++;
        }
    }
    return $score;
}

function analyzeSentiment(?string $feedback, ?int $rating): string
{
    $text = strtolower(trim((string)$feedback));

    $positiveWords = [
        'good', 'great', 'excellent', 'amazing', 'helpful', 'useful', 'clear',
        'organized', 'well organized', 'interesting', 'perfect', 'liked',
        'love', 'best', 'enjoyed', 'valuable', 'friendly', 'professional',
        'bon', 'bien', 'excellent', 'utile', 'clair', 'organise', 'organisee',
        'interessant', 'parfait', 'aime'
    ];
    $negativeWords = [
        'bad', 'poor', 'late', 'delay', 'delayed', 'boring', 'long', 'too long',
        'confusing', 'unclear', 'disorganized', 'bad organization', 'crowded',
        'problem', 'issue', 'weak', 'slow', 'noise', 'noisy', 'waste',
        'terrible', 'awful', 'horrible', 'disappointing', 'not helpful',
        'not useful', 'not good', 'very bad', 'worst', 'useless',
        'mauvais', 'retard', 'ennuyeux', 'longue', 'trop long', 'probleme',
        'mal organise', 'desorganise', 'pas clair', 'inutile'
    ];

    $positiveScore = textContainsAny($text, $positiveWords);
    $negativeScore = textContainsAny($text, $negativeWords);
    $hasExplicitPositiveText = $positiveScore > 0;
    $hasExplicitNegativeText = $negativeScore > 0;

    if ($rating !== null) {
        if ($rating >= 4) {
            $positiveScore += 2;
        } elseif ($rating <= 2) {
            $negativeScore += 2;
        }
    }

    if ($hasExplicitNegativeText && !$hasExplicitPositiveText) {
        return 'Negative';
    }
    if ($hasExplicitPositiveText && !$hasExplicitNegativeText) {
        return 'Positive';
    }
    if ($negativeScore > $positiveScore) {
        return 'Negative';
    }
    if ($positiveScore > $negativeScore) {
        return 'Positive';
    }
    return 'Neutral';
}

function detectComplaintTopics(string $feedback): array
{
    $text = strtolower($feedback);
    $topics = [
        'bad organization' => ['bad organization', 'disorganized', 'mal organise', 'desorganise', 'organization'],
        'late start' => ['late', 'delay', 'delayed', 'retard'],
        'too long' => ['too long', 'long duration', 'longue', 'trop long'],
        'unclear communication' => ['unclear', 'confusing', 'not clear', 'pas clair', 'communication'],
        'crowded venue' => ['crowded', 'too many people', 'venue', 'salle'],
        'technical problems' => ['technical', 'connection', 'bug', 'sound', 'audio', 'projector', 'internet'],
        'low value' => ['bad', 'poor', 'terrible', 'awful', 'horrible', 'disappointing', 'not helpful', 'not useful', 'not good', 'very bad', 'worst', 'useless', 'mauvais', 'inutile'],
    ];

    $found = [];
    foreach ($topics as $topic => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($text, $keyword) !== false) {
                $found[] = $topic;
                break;
            }
        }
    }

    if (empty($found)) {
        $fallbackWords = ['bad', 'poor', 'late', 'long', 'slow', 'boring', 'confusing', 'noise', 'crowded', 'problem', 'terrible', 'awful', 'horrible', 'worst'];
        foreach ($fallbackWords as $word) {
            if (strpos($text, $word) !== false) {
                $found[] = $word;
            }
        }
    }
    return $found;
}

function suggestionForTopic(string $topic): string
{
    $map = [
        'bad organization' => 'Improve event organization with a clear agenda, assigned staff, and pre-event checklist.',
        'late start' => 'Improve event timing by confirming speakers early and starting check-in before the official time.',
        'too long' => 'Reduce duration or split long sessions into shorter blocks with breaks.',
        'unclear communication' => 'Improve communication by sending clear event details, reminders, and expectations before the event.',
        'crowded venue' => 'Control capacity more strictly or choose a larger venue for high-demand events.',
        'technical problems' => 'Test technical equipment, internet, audio, and presentation setup before participants arrive.',
        'low value' => 'Review the event content quality and facilitator delivery, then collect one specific improvement request from each unhappy attendee.',
        'low rating' => 'Follow up on low-rating feedback and ask what should change before repeating this event format.',
    ];
    return $map[$topic] ?? 'Review negative feedback patterns and adjust the next event plan.';
}

try {
    $db = config::getConnexion();
    ensureSentimentColumn($db);

    $sql = "SELECT
                p.participationId,
                p.eventId,
                p.registrationDate,
                p.rating,
                p.feedback,
                e.title AS eventTitle
            FROM Participation p
            LEFT JOIN event e ON e.eventId = p.eventId
            WHERE (p.feedback IS NOT NULL AND TRIM(p.feedback) != '')
               OR p.rating IS NOT NULL";
    $rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    $update = $db->prepare("UPDATE Participation SET sentiment = :sentiment WHERE participationId = :id");
    $stats = ['Positive' => 0, 'Negative' => 0, 'Neutral' => 0];
    $complaints = [];
    $complaintExamples = [];
    $events = [];
    foreach ($rows as $row) {
        $feedback = (string)($row['feedback'] ?? '');
        $rating = $row['rating'] !== null ? (int)$row['rating'] : null;
        $sentiment = analyzeSentiment($feedback, $rating);

        $update->execute([
            ':sentiment' => $sentiment,
            ':id' => (int)$row['participationId'],
        ]);

        $stats[$sentiment]++;

        $eventId = (int)$row['eventId'];
        if (!isset($events[$eventId])) {
            $events[$eventId] = [
                'title' => $row['eventTitle'] ?: 'Event #' . $eventId,
                'ratings' => [],
                'positiveReviews' => 0,
            ];
        }
        if ($rating !== null) {
            $events[$eventId]['ratings'][] = $rating;
        }
        if ($sentiment === 'Positive') {
            $events[$eventId]['positiveReviews']++;
        }

        if ($sentiment === 'Negative') {
            $topics = detectComplaintTopics($feedback);
            if (empty($topics) && $rating !== null && $rating <= 2) {
                $topics = ['low rating'];
            }
            foreach ($topics as $topic) {
                $complaints[$topic] = ($complaints[$topic] ?? 0) + 1;
                if (!isset($complaintExamples[$topic])) {
                    $complaintExamples[$topic] = [];
                }
                if (count($complaintExamples[$topic]) < 2 && trim($feedback) !== '') {
                    $complaintExamples[$topic][] = substr(trim($feedback), 0, 80);
                } elseif (count($complaintExamples[$topic]) < 2 && $rating !== null) {
                    $complaintExamples[$topic][] = 'Rating: ' . $rating . '/5';
                }
            }
        }
    }

    arsort($complaints);
    $topComplaints = [];
    foreach (array_slice($complaints, 0, 5, true) as $topic => $count) {
        $topComplaints[] = [
            'topic' => $topic,
            'count' => $count,
            'examples' => $complaintExamples[$topic] ?? [],
        ];
    }

    $likedEvents = [];
    foreach ($events as $event) {
        $ratingCount = count($event['ratings']);
        if ($ratingCount === 0 && $event['positiveReviews'] === 0) {
            continue;
        }
        $avg = $ratingCount ? array_sum($event['ratings']) / $ratingCount : 0;
        if ($avg >= 4 || $event['positiveReviews'] > 0) {
            $likedEvents[] = [
                'title' => $event['title'],
                'averageRating' => round($avg, 2),
                'positiveReviews' => $event['positiveReviews'],
            ];
        }
    }
    usort($likedEvents, function($a, $b) {
        if ($a['averageRating'] == $b['averageRating']) {
            return $b['positiveReviews'] <=> $a['positiveReviews'];
        }
        return $b['averageRating'] <=> $a['averageRating'];
    });

    $suggestions = [];
    foreach (array_slice(array_keys($complaints), 0, 5) as $topic) {
        $suggestions[] = suggestionForTopic($topic);
    }
    if (empty($suggestions) && $stats['Negative'] > 0) {
        $suggestions[] = 'Review all negative feedback manually and create an improvement checklist for the next events.';
    } elseif (empty($suggestions) && $stats['Neutral'] > 0) {
        $suggestions[] = 'Ask attendees for more specific written feedback so the analyzer can produce clearer recommendations.';
    } elseif (empty($suggestions)) {
        $suggestions[] = 'No risky patterns were detected yet. Keep collecting ratings and written feedback after each event.';
    }

    $total = array_sum($stats);
    echo json_encode([
        'stats' => [
            'totalFeedbacks' => $total,
            'positive' => $stats['Positive'],
            'negative' => $stats['Negative'],
            'neutral' => $stats['Neutral'],
            'positivePercent' => $total ? round($stats['Positive'] * 100 / $total, 1) : 0,
            'negativePercent' => $total ? round($stats['Negative'] * 100 / $total, 1) : 0,
            'neutralPercent' => $total ? round($stats['Neutral'] * 100 / $total, 1) : 0,
        ],
        'topComplaints' => $topComplaints,
        'mostLikedEvents' => array_slice($likedEvents, 0, 3),
        'suggestions' => array_values(array_unique($suggestions)),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
