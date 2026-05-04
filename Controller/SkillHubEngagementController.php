<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../Model/SkillHubEngagement.php';

class SkillHubEngagementController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = config::getConnexion();
    }

    private function normalizeDate(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return substr(str_replace('T', ' ', trim($value)), 0, 19);
    }

    private function extractResponseText(array $payload): string
    {
        $choiceText = trim((string) ($payload['choices'][0]['message']['content'] ?? ''));
        if ($choiceText !== '') {
            return $choiceText;
        }

        $outputText = trim((string) ($payload['output_text'] ?? ''));
        if ($outputText !== '') {
            return $outputText;
        }

        $parts = [];
        foreach (($payload['output'] ?? []) as $outputItem) {
            foreach (($outputItem['content'] ?? []) as $contentItem) {
                $text = trim((string) ($contentItem['text'] ?? ''));
                if ($text !== '') {
                    $parts[] = $text;
                }
            }
        }

        return trim(implode("\n\n", $parts));
    }

    public function getAllPosts(): array
    {
        $query = $this->pdo->query("SELECT * FROM Post ORDER BY postId DESC");
        return $query->fetchAll();
    }

    public function getPostById(int $postId): ?array
    {
        $query = $this->pdo->prepare("SELECT * FROM Post WHERE postId = :postId");
        $query->execute(['postId' => $postId]);
        $row = $query->fetch();
        return $row ?: null;
    }

    public function createPost(PostEntity $post): bool
    {
        $query = $this->pdo->prepare(
            "INSERT INTO Post (
                groupId, userId, challengeId, postType, title, content, status, linkedUrl, createdAt
             ) VALUES (
                :groupId, :userId, :challengeId, :postType, :title, :content, :status, :linkedUrl, :createdAt
             )"
        );

        return $query->execute([
            'groupId' => $post->getGroupId(),
            'userId' => $post->getUserId(),
            'challengeId' => $post->getChallengeId(),
            'postType' => $post->getPostType(),
            'title' => $post->getTitle(),
            'content' => $post->getContent(),
            'status' => $post->getStatus(),
            'linkedUrl' => $post->getLinkedUrl(),
            'createdAt' => $this->normalizeDate($post->getCreatedAt()) ?? date('Y-m-d H:i:s'),
        ]);
    }

    public function updatePost(int $postId, PostEntity $post): bool
    {
        $query = $this->pdo->prepare(
            "UPDATE Post
             SET groupId = :groupId,
                 userId = :userId,
                 challengeId = :challengeId,
                 postType = :postType,
                 title = :title,
                 content = :content,
                 status = :status,
                 linkedUrl = :linkedUrl
             WHERE postId = :postId"
        );

        return $query->execute([
            'postId' => $postId,
            'groupId' => $post->getGroupId(),
            'userId' => $post->getUserId(),
            'challengeId' => $post->getChallengeId(),
            'postType' => $post->getPostType(),
            'title' => $post->getTitle(),
            'content' => $post->getContent(),
            'status' => $post->getStatus(),
            'linkedUrl' => $post->getLinkedUrl(),
        ]);
    }

    public function deletePost(int $postId): bool
    {
        $query = $this->pdo->prepare("DELETE FROM Post WHERE postId = :postId");
        return $query->execute(['postId' => $postId]);
    }

    public function getAllComments(): array
    {
        $query = $this->pdo->query("SELECT * FROM Comments ORDER BY commentId DESC");
        return $query->fetchAll();
    }

    public function getCommentById(int $commentId): ?array
    {
        $query = $this->pdo->prepare("SELECT * FROM Comments WHERE commentId = :commentId");
        $query->execute(['commentId' => $commentId]);
        $row = $query->fetch();
        return $row ?: null;
    }

    public function createComment(CommentEntity $comment): bool
    {
        $query = $this->pdo->prepare(
            "INSERT INTO Comments (
                postId, userId, parentCommentId, content, likesCount, status, createdAt
             ) VALUES (
                :postId, :userId, :parentCommentId, :content, :likesCount, :status, :createdAt
             )"
        );

        return $query->execute([
            'postId' => $comment->getPostId(),
            'userId' => $comment->getUserId(),
            'parentCommentId' => $comment->getParentCommentId(),
            'content' => $comment->getContent(),
            'likesCount' => $comment->getLikesCount() ?? 0,
            'status' => $comment->getStatus() ?? 'active',
            'createdAt' => $this->normalizeDate($comment->getCreatedAt()) ?? date('Y-m-d H:i:s'),
        ]);
    }

    public function updateComment(int $commentId, CommentEntity $comment): bool
    {
        $query = $this->pdo->prepare(
            "UPDATE Comments
             SET postId = :postId,
                 userId = :userId,
                 parentCommentId = :parentCommentId,
                 content = :content,
                 likesCount = :likesCount,
                 status = :status
             WHERE commentId = :commentId"
        );

        return $query->execute([
            'commentId' => $commentId,
            'postId' => $comment->getPostId(),
            'userId' => $comment->getUserId(),
            'parentCommentId' => $comment->getParentCommentId(),
            'content' => $comment->getContent(),
            'likesCount' => $comment->getLikesCount() ?? 0,
            'status' => $comment->getStatus() ?? 'active',
        ]);
    }

    public function deleteComment(int $commentId): bool
    {
        $query = $this->pdo->prepare("DELETE FROM Comments WHERE commentId = :commentId");
        return $query->execute(['commentId' => $commentId]);
    }

    public function getAllSubmissions(): array
    {
        $query = $this->pdo->query("SELECT * FROM Submission ORDER BY submissionId DESC");
        return $query->fetchAll();
    }

    public function getSubmissionById(int $submissionId): ?array
    {
        $query = $this->pdo->prepare("SELECT * FROM Submission WHERE submissionId = :submissionId");
        $query->execute(['submissionId' => $submissionId]);
        $row = $query->fetch();
        return $row ?: null;
    }

    public function getSubmissionsByChallenge(int $challengeId): array
    {
        $query = $this->pdo->prepare(
            "SELECT
                s.*,
                gm.userId,
                u.fullName,
                u.role,
                c.title AS challengeTitle
             FROM Submission s
             LEFT JOIN GroupMember gm ON gm.groupMemberId = s.groupMemberId
             LEFT JOIN Users u ON u.userId = gm.userId
             LEFT JOIN Challenge c ON c.challengeId = s.challengeId
             WHERE s.challengeId = :challengeId
             ORDER BY
                CASE WHEN s.score IS NULL THEN 1 ELSE 0 END ASC,
                s.score DESC,
                s.submittedAt DESC,
                s.submissionId DESC"
        );
        $query->execute(['challengeId' => $challengeId]);
        return $query->fetchAll();
    }

    public function createSubmission(SubmissionEntity $submission): bool
    {
        $query = $this->pdo->prepare(
            "INSERT INTO Submission (
                groupMemberId, challengeId, projectLink, description, submittedAt, score, submissionRank, status
             ) VALUES (
                :groupMemberId, :challengeId, :projectLink, :description, :submittedAt, :score, :submissionRank, :status
             )"
        );

        return $query->execute([
            'groupMemberId' => $submission->getGroupMemberId(),
            'challengeId' => $submission->getChallengeId(),
            'projectLink' => $submission->getProjectLink(),
            'description' => $submission->getDescription(),
            'submittedAt' => $this->normalizeDate($submission->getSubmittedAt()) ?? date('Y-m-d'),
            'score' => $submission->getScore(),
            'submissionRank' => $submission->getSubmissionRank(),
            'status' => $submission->getStatus(),
        ]);
    }

    public function updateSubmission(int $submissionId, SubmissionEntity $submission): bool
    {
        $query = $this->pdo->prepare(
            "UPDATE Submission
             SET groupMemberId = :groupMemberId,
                 challengeId = :challengeId,
                 projectLink = :projectLink,
                 description = :description,
                 submittedAt = :submittedAt,
                 score = :score,
                 submissionRank = :submissionRank,
                 status = :status
             WHERE submissionId = :submissionId"
        );

        return $query->execute([
            'submissionId' => $submissionId,
            'groupMemberId' => $submission->getGroupMemberId(),
            'challengeId' => $submission->getChallengeId(),
            'projectLink' => $submission->getProjectLink(),
            'description' => $submission->getDescription(),
            'submittedAt' => $this->normalizeDate($submission->getSubmittedAt()),
            'score' => $submission->getScore(),
            'submissionRank' => $submission->getSubmissionRank(),
            'status' => $submission->getStatus(),
        ]);
    }

    public function deleteSubmission(int $submissionId): bool
    {
        $query = $this->pdo->prepare("DELETE FROM Submission WHERE submissionId = :submissionId");
        return $query->execute(['submissionId' => $submissionId]);
    }

    public function evaluateSubmission(int $submissionId, int $score, string $status): bool
    {
        $query = $this->pdo->prepare(
            "UPDATE Submission
             SET score = :score,
                 status = :status
             WHERE submissionId = :submissionId"
        );

        $updated = $query->execute([
            'submissionId' => $submissionId,
            'score' => $score,
            'status' => $status,
        ]);

        if (!$updated) {
            return false;
        }

        $submission = $this->getSubmissionById($submissionId);
        if ($submission === null || empty($submission['challengeId'])) {
            return true;
        }

        $this->refreshSubmissionRanks((int) $submission['challengeId']);
        return true;
    }

    public function refreshSubmissionRanks(int $challengeId): void
    {
        $submissions = $this->getSubmissionsByChallenge($challengeId);
        $rank = 1;

        $query = $this->pdo->prepare(
            "UPDATE Submission
             SET submissionRank = :submissionRank
             WHERE submissionId = :submissionId"
        );

        foreach ($submissions as $submission) {
            $query->execute([
                'submissionRank' => $rank++,
                'submissionId' => (int) $submission['submissionId'],
            ]);
        }
    }

    public function generateSubmissionAiFeedback(array $challenge, array $submission, string $audience = 'manager'): array
    {
        $apiKey = config::getHuggingFaceApiKey();
        if ($apiKey === '') {
            $apiKey = getenv('HF_TOKEN') ?: ($_ENV['HF_TOKEN'] ?? '');
        }

        if ($apiKey === null || trim($apiKey) === '') {
            return [
                'ok' => false,
                'error' => 'Add your Hugging Face API key in config.php before generating AI feedback.',
            ];
        }

        $model = config::getHuggingFaceFeedbackModel();
        if ($model === '') {
            $model = getenv('HF_FEEDBACK_MODEL') ?: ($_ENV['HF_FEEDBACK_MODEL'] ?? 'google/gemma-2-2b-it:hf-inference');
        }
        $challengeTitle = trim((string) ($challenge['title'] ?? 'Untitled challenge'));
        $challengeDescription = trim((string) ($challenge['description'] ?? 'No challenge description provided.'));
        $challengeDifficulty = trim((string) ($challenge['difficulty'] ?? 'Unknown'));
        $submissionDescription = trim((string) ($submission['description'] ?? 'No submission description provided.'));
        $submissionLink = trim((string) ($submission['projectLink'] ?? ''));
        $studentName = trim((string) ($submission['fullName'] ?? 'This student'));
        $score = $submission['score'] !== null ? (string) $submission['score'] : 'Not scored yet';
        $status = trim((string) ($submission['status'] ?? 'submitted'));

        $isStudentAudience = strtolower(trim($audience)) === 'student';
        $systemPrompt = $isStudentAudience
            ? 'You are an academic submission reviewer inside a student skill hub platform. '
                . 'Write short, practical feedback directly for the student who submitted the work. '
                . 'Return exactly three short sections with these labels: Strengths, Improvements, Next step. '
                . 'Be encouraging, specific, and concise. Avoid long markdown lists.'
            : 'You are an academic submission reviewer inside a student skill hub platform. '
                . 'Write short, practical feedback for a manager. '
                . 'Return exactly three short sections with these labels: Strengths, Weaknesses, Next step. '
                . 'Be constructive, specific, and concise. Avoid long markdown lists.';
        $userInstruction = $isStudentAudience
            ? 'Generate feedback for the student so they understand what worked and what to improve.'
            : 'Generate feedback for the manager reviewing this submission.';

        $payload = json_encode([
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $systemPrompt,
                ],
                [
                    'role' => 'user',
                    'content' => "Challenge title: {$challengeTitle}\n"
                        . "Challenge difficulty: {$challengeDifficulty}\n"
                        . "Challenge brief: {$challengeDescription}\n\n"
                        . "Student: {$studentName}\n"
                        . "Submission status: {$status}\n"
                        . "Current score: {$score}\n"
                        . "Project link: " . ($submissionLink !== '' ? $submissionLink : 'No link provided') . "\n"
                        . "Submission description: {$submissionDescription}\n\n"
                        . $userInstruction,
                ],
            ],
            'max_tokens' => 280,
            'temperature' => 0.6,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($payload === false) {
            return [
                'ok' => false,
                'error' => 'Could not prepare the AI request payload.',
            ];
        }

        $curl = curl_init('https://router.huggingface.co/v1/chat/completions');
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 45,
        ]);

        $rawResponse = curl_exec($curl);
        $curlError = curl_error($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($rawResponse === false) {
            return [
                'ok' => false,
                'error' => $curlError !== '' ? $curlError : 'The AI request failed before a response was returned.',
            ];
        }

        $decoded = json_decode($rawResponse, true);
        if (!is_array($decoded)) {
            return [
                'ok' => false,
                'error' => 'The AI response could not be decoded.',
            ];
        }

        if ($httpCode >= 400) {
            $apiError = $decoded['error']['message'] ?? 'The AI API returned an error.';
            return [
                'ok' => false,
                'error' => (string) $apiError,
            ];
        }

        $feedback = $this->extractResponseText($decoded);
        if ($feedback === '') {
            return [
                'ok' => false,
                'error' => 'The AI returned an empty feedback response.',
            ];
        }

        return [
            'ok' => true,
            'feedback' => $feedback,
            'model' => $model,
        ];
    }
}
