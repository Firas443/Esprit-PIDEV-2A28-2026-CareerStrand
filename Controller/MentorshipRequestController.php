<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../Model/MentorshipRequest.php';

class MentorshipRequestController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = config::getConnexion();
        $this->ensureSchema();
    }

    private function ensureSchema(): void
    {
        try {
            $this->pdo->exec(
                "CREATE TABLE IF NOT EXISTS mentorship_request (
                    requestId INT(11) NOT NULL AUTO_INCREMENT,
                    requesterId INT(11) NOT NULL,
                    mentorId INT(11) NOT NULL,
                    sourceType VARCHAR(20) NOT NULL DEFAULT 'direct',
                    sourceId INT(11) DEFAULT NULL,
                    requestType VARCHAR(30) NOT NULL DEFAULT 'mentor_chat',
                    sessionMode VARCHAR(20) NOT NULL DEFAULT 'chat',
                    subject VARCHAR(255) NOT NULL,
                    details TEXT NOT NULL,
                    preferredDate DATE DEFAULT NULL,
                    preferredTime TIME DEFAULT NULL,
                    durationMinutes INT(11) NOT NULL DEFAULT 30,
                    offerAmount DECIMAL(10,2) DEFAULT NULL,
                    currency VARCHAR(10) NOT NULL DEFAULT 'USD',
                    status VARCHAR(20) NOT NULL DEFAULT 'pending',
                    responseNote TEXT DEFAULT NULL,
                    scheduledDate DATE DEFAULT NULL,
                    scheduledTime TIME DEFAULT NULL,
                    meetingLink VARCHAR(255) DEFAULT NULL,
                    createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    respondedAt DATETIME DEFAULT NULL,
                    PRIMARY KEY (requestId),
                    KEY idx_mentor_status (mentorId, status, createdAt),
                    KEY idx_requester_status (requesterId, status, createdAt),
                    KEY idx_source (sourceType, sourceId)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
            );
            $this->pdo->exec(
                "CREATE TABLE IF NOT EXISTS mentorship_request_message (
                    messageId INT(11) NOT NULL AUTO_INCREMENT,
                    requestId INT(11) NOT NULL,
                    senderId INT(11) NOT NULL,
                    message TEXT NOT NULL,
                    createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (messageId),
                    KEY idx_request (requestId, createdAt),
                    KEY idx_sender (senderId, createdAt)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
            );
        } catch (PDOException $e) {
        }
    }

    public function getRequestById(int $requestId): ?array
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT mr.*,
                        ru.fullName AS requesterName, ru.role AS requesterRole, ru.email AS requesterEmail,
                        mu.fullName AS mentorName, mu.role AS mentorRole, mu.email AS mentorEmail
                 FROM mentorship_request mr
                 LEFT JOIN Users ru ON ru.userId = mr.requesterId
                 LEFT JOIN Users mu ON mu.userId = mr.mentorId
                 WHERE mr.requestId = ?
                 LIMIT 1"
            );
            $stmt->execute([$requestId]);
            $row = $stmt->fetch();
            return $row ?: null;
        } catch (PDOException $e) {
            return null;
        }
    }

    public function getRequestsByRequester(int $requesterId): array
    {
        return $this->getRequests('mr.requesterId = :userId', [':userId' => $requesterId]);
    }

    public function getRequestsByMentor(int $mentorId): array
    {
        return $this->getRequests('mr.mentorId = :userId', [':userId' => $mentorId]);
    }

    public function listEligibleMentors(int $limit = 100): array
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT u.userId, u.fullName, u.email, u.role, u.status, u.approvalStatus,
                        p.completionScore, p.level, p.careerGoal
                 FROM Users u
                 LEFT JOIN Profile p ON p.userId = u.userId
                 WHERE LOWER(COALESCE(u.status, '')) = 'active'
                   AND LOWER(COALESCE(u.approvalStatus, 'approved')) = 'approved'
                   AND LOWER(COALESCE(u.role, '')) IN ('admin', 'manager', 'manager recruiter')
                 ORDER BY CASE
                    WHEN LOWER(u.role) = 'admin' THEN 0
                    ELSE 1
                 END,
                 COALESCE(p.completionScore, 0) DESC,
                 u.fullName ASC
                 LIMIT :limit"
            );
            $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll() ?: [];

            return array_values(array_filter($rows, static function (array $row): bool {
                $role = strtolower(trim((string) ($row['role'] ?? '')));
                $score = (int) ($row['completionScore'] ?? 0);
                return in_array($role, ['admin', 'manager', 'manager recruiter'], true) && $score >= 0;
            }));
        } catch (PDOException $e) {
            return [];
        }
    }

    public function createRequest(array $data): array
    {
        $requesterId = (int) ($data['requesterId'] ?? 0);
        $mentorId = (int) ($data['mentorId'] ?? 0);
        $subject = trim((string) ($data['subject'] ?? ''));
        $details = trim((string) ($data['details'] ?? ''));
        $sourceType = $this->normalizeSourceType((string) ($data['sourceType'] ?? 'direct'));
        $sourceId = isset($data['sourceId']) && (int) $data['sourceId'] > 0 ? (int) $data['sourceId'] : null;
        $requestType = $this->normalizeRequestType((string) ($data['requestType'] ?? 'mentor_chat'));
        $sessionMode = $this->normalizeSessionMode((string) ($data['sessionMode'] ?? 'chat'));
        $preferredDate = $this->normalizeNullableString($data['preferredDate'] ?? null);
        $preferredTime = $this->normalizeNullableString($data['preferredTime'] ?? null);
        $durationMinutes = max(15, min(240, (int) ($data['durationMinutes'] ?? 30)));
        $offerAmount = $this->normalizeMoney($data['offerAmount'] ?? null);
        $currency = $this->normalizeCurrency((string) ($data['currency'] ?? 'USD'));

        $errors = [];
        if ($requesterId <= 0) {
            $errors['requesterId'] = 'Requester account is required.';
        }
        if ($mentorId <= 0) {
            $errors['mentorId'] = 'Please choose a mentor.';
        }
        if ($requesterId > 0 && $mentorId > 0 && $requesterId === $mentorId) {
            $errors['mentorId'] = 'You cannot request mentorship from yourself.';
        }
        if ($subject === '') {
            $errors['subject'] = 'Subject is required.';
        }
        if ($details === '') {
            $errors['details'] = 'Please add a short brief for the mentor.';
        }
        if (!$this->isEligibleMentor($mentorId)) {
            $errors['mentorId'] = 'That admin, manager, or manager recruiter is not currently available for mentorship requests.';
        }
        if ($preferredDate !== null && !$this->isValidDate($preferredDate)) {
            $errors['preferredDate'] = 'Preferred date must be valid.';
        }
        if ($preferredTime !== null && !$this->isValidTime($preferredTime)) {
            $errors['preferredTime'] = 'Preferred time must be valid.';
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO mentorship_request (
                    requesterId, mentorId, sourceType, sourceId, requestType, sessionMode,
                    subject, details, preferredDate, preferredTime, durationMinutes, offerAmount, currency, status
                 ) VALUES (
                    :requesterId, :mentorId, :sourceType, :sourceId, :requestType, :sessionMode,
                    :subject, :details, :preferredDate, :preferredTime, :durationMinutes, :offerAmount, :currency, 'pending'
                 )"
            );
            $stmt->execute([
                ':requesterId' => $requesterId,
                ':mentorId' => $mentorId,
                ':sourceType' => $sourceType,
                ':sourceId' => $sourceId,
                ':requestType' => $requestType,
                ':sessionMode' => $sessionMode,
                ':subject' => $subject,
                ':details' => $details,
                ':preferredDate' => $preferredDate,
                ':preferredTime' => $preferredTime,
                ':durationMinutes' => $durationMinutes,
                ':offerAmount' => $offerAmount,
                ':currency' => $currency,
            ]);

            return ['success' => true, 'requestId' => (int) $this->pdo->lastInsertId()];
        } catch (PDOException $e) {
            return ['success' => false, 'errors' => ['db' => 'Could not create mentorship request.']];
        }
    }

    public function respondToRequest(int $requestId, int $mentorId, array $data): array
    {
        $request = $this->getRequestById($requestId);
        if (!$request) {
            return ['success' => false, 'errors' => ['requestId' => 'Request not found.']];
        }

        if ((int) ($request['mentorId'] ?? 0) !== $mentorId) {
            return ['success' => false, 'errors' => ['requestId' => 'You are not allowed to edit this request.']];
        }

        $status = $this->normalizeStatus((string) ($data['status'] ?? 'accepted'));
        $responseNote = trim((string) ($data['responseNote'] ?? ''));
        $scheduledDate = $this->normalizeNullableString($data['scheduledDate'] ?? null);
        $scheduledTime = $this->normalizeNullableString($data['scheduledTime'] ?? null);
        $meetingLink = trim((string) ($data['meetingLink'] ?? ''));

        if (in_array($status, ['accepted', 'scheduled'], true)) {
            if ($scheduledDate !== null && !$this->isValidDate($scheduledDate)) {
                return ['success' => false, 'errors' => ['scheduledDate' => 'Scheduled date must be valid.']];
            }
            if ($scheduledTime !== null && !$this->isValidTime($scheduledTime)) {
                return ['success' => false, 'errors' => ['scheduledTime' => 'Scheduled time must be valid.']];
            }
        }

        try {
            $stmt = $this->pdo->prepare(
                "UPDATE mentorship_request
                 SET status = :status,
                     responseNote = :responseNote,
                     scheduledDate = :scheduledDate,
                     scheduledTime = :scheduledTime,
                     meetingLink = :meetingLink,
                     respondedAt = NOW(),
                     updatedAt = NOW()
                 WHERE requestId = :requestId AND mentorId = :mentorId"
            );
            $stmt->execute([
                ':status' => $status,
                ':responseNote' => $responseNote !== '' ? $responseNote : null,
                ':scheduledDate' => $scheduledDate,
                ':scheduledTime' => $scheduledTime,
                ':meetingLink' => $meetingLink !== '' ? $meetingLink : null,
                ':requestId' => $requestId,
                ':mentorId' => $mentorId,
            ]);

            return ['success' => true];
        } catch (PDOException $e) {
            return ['success' => false, 'errors' => ['db' => 'Could not update mentorship request.']];
        }
    }

    public function getMessagesByRequestId(int $requestId): array
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT m.*, u.fullName AS senderName, u.role AS senderRole
                 FROM mentorship_request_message m
                 LEFT JOIN Users u ON u.userId = m.senderId
                 WHERE m.requestId = ?
                 ORDER BY m.createdAt ASC, m.messageId ASC"
            );
            $stmt->execute([$requestId]);
            return $stmt->fetchAll() ?: [];
        } catch (PDOException $e) {
            return [];
        }
    }

    public function addMessage(int $requestId, int $senderId, string $message): array
    {
        $message = trim($message);
        if ($requestId <= 0 || $senderId <= 0 || $message === '') {
            return ['success' => false, 'errors' => ['message' => 'Message is required.']];
        }

        $request = $this->getRequestById($requestId);
        if (!$request) {
            return ['success' => false, 'errors' => ['requestId' => 'Request not found.']];
        }

        $requestStatus = strtolower(trim((string) ($request['status'] ?? 'pending')));
        if (!in_array($requestStatus, ['accepted', 'scheduled', 'completed'], true)) {
            return ['success' => false, 'errors' => ['message' => 'Messaging opens after the request is accepted.']];
        }

        $allowedSenderIds = [
            (int) ($request['requesterId'] ?? 0),
            (int) ($request['mentorId'] ?? 0),
        ];
        if (!in_array($senderId, $allowedSenderIds, true)) {
            return ['success' => false, 'errors' => ['message' => 'You are not allowed to message on this request.']];
        }

        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO mentorship_request_message (requestId, senderId, message)
                 VALUES (:requestId, :senderId, :message)"
            );
            $stmt->execute([
                ':requestId' => $requestId,
                ':senderId' => $senderId,
                ':message' => $message,
            ]);

            return ['success' => true, 'messageId' => (int) $this->pdo->lastInsertId()];
        } catch (PDOException $e) {
            return ['success' => false, 'errors' => ['db' => 'Could not save message.']];
        }
    }

    public function isEligibleMentor(int $mentorId): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT u.role, u.status, u.approvalStatus, COALESCE(p.completionScore, 0) AS completionScore
                 FROM Users u
                 LEFT JOIN Profile p ON p.userId = u.userId
                 WHERE u.userId = ?
                 LIMIT 1"
            );
            $stmt->execute([$mentorId]);
            $row = $stmt->fetch();
            if (!$row) {
                return false;
            }

            $role = strtolower(trim((string) ($row['role'] ?? '')));
            $status = strtolower(trim((string) ($row['status'] ?? '')));
            $approvalStatus = strtolower(trim((string) ($row['approvalStatus'] ?? 'approved')));
            $score = (int) ($row['completionScore'] ?? 0);

            if ($status !== 'active' || $approvalStatus !== 'approved') {
                return false;
            }

            return in_array($role, ['admin', 'manager', 'manager recruiter'], true);
        } catch (PDOException $e) {
            return false;
        }
    }

    private function getRequests(string $where, array $params): array
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT mr.*,
                        ru.fullName AS requesterName, ru.role AS requesterRole, ru.email AS requesterEmail,
                        mu.fullName AS mentorName, mu.role AS mentorRole, mu.email AS mentorEmail
                 FROM mentorship_request mr
                 LEFT JOIN Users ru ON ru.userId = mr.requesterId
                 LEFT JOIN Users mu ON mu.userId = mr.mentorId
                 WHERE $where
                 ORDER BY mr.createdAt DESC, mr.requestId DESC"
            );
            $stmt->execute($params);
            return $stmt->fetchAll() ?: [];
        } catch (PDOException $e) {
            return [];
        }
    }

    private function normalizeSourceType(string $value): string
    {
        $value = strtolower(trim($value));
        return in_array($value, ['profile', 'hub', 'event', 'direct'], true) ? $value : 'direct';
    }

    private function normalizeRequestType(string $value): string
    {
        $value = strtolower(trim($value));
        return in_array($value, ['mentor_chat', 'office_hours'], true) ? $value : 'mentor_chat';
    }

    private function normalizeSessionMode(string $value): string
    {
        $value = strtolower(trim($value));
        return in_array($value, ['chat', 'video', 'in_person'], true) ? $value : 'chat';
    }

    private function normalizeStatus(string $value): string
    {
        $value = strtolower(trim($value));
        return in_array($value, ['pending', 'accepted', 'declined', 'scheduled', 'completed', 'cancelled'], true) ? $value : 'pending';
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value !== '' ? $value : null;
    }

    private function normalizeMoney(mixed $value): ?float
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function normalizeCurrency(string $value): string
    {
        $value = strtoupper(trim($value));
        return preg_match('/^[A-Z]{3}$/', $value) ? $value : 'USD';
    }

    private function isValidDate(?string $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        $date = DateTime::createFromFormat('Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value;
    }

    private function isValidTime(?string $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        $time = DateTime::createFromFormat('H:i:s', $value) ?: DateTime::createFromFormat('H:i', $value);
        return $time !== false;
    }
}
?>
