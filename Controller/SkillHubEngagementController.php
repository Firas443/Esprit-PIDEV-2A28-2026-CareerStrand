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
}
