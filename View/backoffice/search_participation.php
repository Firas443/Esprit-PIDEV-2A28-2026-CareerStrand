<?php
/**
 * search_participation.php  (BackOffice)
 * Returns all participations with real user name/email and event title from DB JOINs.
 */
require_once __DIR__ . '/../../Controller/ParticipationController.php';
require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json');

try {
    $db  = config::getConnexion();

    // JOIN participation with user and event to get real names
    $sql = "
        SELECT
            p.participationId,
            p.userId,
            COALESCE(u.fullName, '') AS userName,
            COALESCE(u.email,    '') AS userEmail,
            p.eventId,
            COALESCE(e.title,   '') AS eventTitle,
            p.registrationDate,
            p.attendanceStatus,
            p.status,
            p.rating,
            p.feedback
        FROM Participation p
        LEFT JOIN Users  u ON u.userId  = p.userId
        LEFT JOIN event e ON e.eventId = p.eventId
        ORDER BY
            CASE WHEN p.attendanceStatus = 'Pending' THEN 0 ELSE 1 END,
            p.registrationDate DESC,
            p.participationId DESC
    ";

    $stmt = $db->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = array_map(function($row) {
        // If userName is empty (userId was NULL = front registration),
        // try to parse it from the feedback column "name|email"
        $userName  = $row['userName'];
        $userEmail = $row['userEmail'];
        $feedback  = $row['feedback'] ?? '';

        if (empty($userName) && !empty($feedback) && strpos($feedback, '|') !== false) {
            $parts     = explode('|', $feedback, 2);
            $userName  = $parts[0];
            $userEmail = $parts[1];
            $feedback  = ''; // clear it — it was just a temp store
        }

        return [
            'participationId'  => (int)$row['participationId'],
            'userId'           => $row['userId'],
            'userName'         => $userName  ?: 'Anonyme',
            'userEmail'        => $userEmail ?: '',
            'eventId'          => (int)$row['eventId'],
            'eventTitle'       => $row['eventTitle'],
            'registrationDate' => $row['registrationDate'],
            'attendanceStatus' => $row['attendanceStatus'],
            'status'           => $row['attendanceStatus'], // alias
            'rating'           => $row['rating'] !== null ? (int)$row['rating'] : null,
            'feedbackText'     => $feedback,
        ];
    }, $rows);

    echo json_encode($data);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
