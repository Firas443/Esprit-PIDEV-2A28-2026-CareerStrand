<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../Model/Courses.php';

class ControlCourses {
    private $lastInsertedId = null;

    private function ensureCertificateSchema(PDO $db): void {
        $columns = [
            'certificateTemplateType' => "ALTER TABLE `course` ADD COLUMN `certificateTemplateType` VARCHAR(20) NOT NULL DEFAULT 'preset' AFTER `createdAt`",
            'certificatePresetKey' => "ALTER TABLE `course` ADD COLUMN `certificatePresetKey` VARCHAR(50) NOT NULL DEFAULT 'nvidia' AFTER `certificateTemplateType`",
            'certificateBackgroundUrl' => "ALTER TABLE `course` ADD COLUMN `certificateBackgroundUrl` VARCHAR(255) DEFAULT NULL AFTER `certificatePresetKey`",
            'certificateLogoUrl' => "ALTER TABLE `course` ADD COLUMN `certificateLogoUrl` VARCHAR(255) DEFAULT NULL AFTER `certificateBackgroundUrl`",
            'certificateIssuer' => "ALTER TABLE `course` ADD COLUMN `certificateIssuer` VARCHAR(120) NOT NULL DEFAULT 'CareerStrand' AFTER `certificateLogoUrl`",
            'certificateTitle' => "ALTER TABLE `course` ADD COLUMN `certificateTitle` VARCHAR(120) NOT NULL DEFAULT 'Certificate of Completion' AFTER `certificateIssuer`",
            'certificateSubtitle' => "ALTER TABLE `course` ADD COLUMN `certificateSubtitle` VARCHAR(255) NOT NULL DEFAULT 'This certifies that' AFTER `certificateTitle`",
            'certificateSignerName' => "ALTER TABLE `course` ADD COLUMN `certificateSignerName` VARCHAR(120) NOT NULL DEFAULT 'Course Manager' AFTER `certificateSubtitle`",
            'certificateSignatureData' => "ALTER TABLE `course` ADD COLUMN `certificateSignatureData` LONGTEXT NULL AFTER `certificateSignerName`",
        ];

        foreach ($columns as $column => $sql) {
            try {
                $stmt = $db->prepare('SHOW COLUMNS FROM `course` LIKE ?');
                $stmt->execute([$column]);
                if (!$stmt->fetch()) {
                    $db->exec($sql);
                }
            } catch (Throwable $e) {
                // Keep the course module usable even if schema changes are restricted.
            }
        }
    }

    private function courseSelectSql(): string {
        return "SELECT
                    courseId AS CourseID,
                    title AS Title,
                    description AS Description,
                    category AS Categorie,
                    skill AS Skill,
                    difficulty AS Difficulty,
                    duration AS Duration,
                    status AS Statut,
                    createdAt AS Published_AT,
                    certificateTemplateType,
                    certificatePresetKey,
                    certificateBackgroundUrl,
                    certificateLogoUrl,
                    certificateIssuer,
                    certificateTitle,
                    certificateSubtitle,
                    certificateSignerName,
                    certificateSignatureData,
                    NULL AS upload_video
                FROM course";
    }

    private function nextCourseId(PDO $db): int {
        $this->ensureCertificateSchema($db);
        return (int)$db->query('SELECT COALESCE(MAX(courseId), 0) + 1 FROM course')->fetchColumn();
    }

    public function listeCourse() {
        $db = config::getConnexion();
        try {
            $this->ensureCertificateSchema($db);
            return $db->query($this->courseSelectSql() . ' ORDER BY courseId DESC');
        } catch(Exception $e) {
            die('Erreur:' . $e->getMessage());
        }
    }

    public function addCourse($courses) {
        $db = config::getConnexion();
        try {
            $this->ensureCertificateSchema($db);
            $courseId = $this->nextCourseId($db);
            $req = $db->prepare(
                'INSERT INTO course (
                    courseId, title, description, category, skill, difficulty, duration, status, createdAt,
                    certificateTemplateType, certificatePresetKey, certificateBackgroundUrl, certificateLogoUrl,
                    certificateIssuer, certificateTitle, certificateSubtitle, certificateSignerName, certificateSignatureData
                 )
                 VALUES (
                    :courseId, :title, :description, :category, :skill, :difficulty, :duration, :status, :createdAt,
                    :certificateTemplateType, :certificatePresetKey, :certificateBackgroundUrl, :certificateLogoUrl,
                    :certificateIssuer, :certificateTitle, :certificateSubtitle, :certificateSignerName, :certificateSignatureData
                 )'
            );
            $req->execute([
                'courseId'    => $courseId,
                'title'       => $courses->getTitle(),
                'description' => $courses->getDescription(),
                'category'    => $courses->getCategorie(),
                'skill'       => $courses->getSkill(),
                'difficulty'  => $courses->getDifficulty(),
                'duration'    => $courses->getDuration(),
                'status'      => $courses->getStatut(),
                'createdAt'   => $courses->getPublished_AT()->format('Y-m-d'),
                'certificateTemplateType' => $courses->getCertificateTemplateType() ?: 'preset',
                'certificatePresetKey' => $courses->getCertificatePresetKey() ?: 'nvidia',
                'certificateBackgroundUrl' => $courses->getCertificateBackgroundUrl(),
                'certificateLogoUrl' => $courses->getCertificateLogoUrl(),
                'certificateIssuer' => $courses->getCertificateIssuer() ?: 'CareerStrand',
                'certificateTitle' => $courses->getCertificateTitle() ?: 'Certificate of Completion',
                'certificateSubtitle' => $courses->getCertificateSubtitle() ?: 'This certifies that',
                'certificateSignerName' => $courses->getCertificateSignerName() ?: 'Course Manager',
                'certificateSignatureData' => $courses->getCertificateSignatureData(),
            ]);
            $this->lastInsertedId = $courseId;
            return $courseId;
        } catch(Exception $e) {
            die('Erreur:' . $e->getMessage());
        }
    }

    public function deleteCourse($id) {
        $db = config::getConnexion();
        try {
            $req = $db->prepare('DELETE FROM course WHERE courseId = :courseId');
            $req->execute(['courseId' => $id]);
        } catch(Exception $e) {
            die('Erreur: ' . $e->getMessage());
        }
    }

    public function updateCourse($courses, $id) {
        $db = config::getConnexion();
        try {
            $this->ensureCertificateSchema($db);
            $req = $db->prepare(
                'UPDATE course
                 SET title = :title,
                     description = :description,
                     category = :category,
                     skill = :skill,
                     difficulty = :difficulty,
                     duration = :duration,
                     status = :status,
                     createdAt = :createdAt,
                     certificateTemplateType = :certificateTemplateType,
                     certificatePresetKey = :certificatePresetKey,
                     certificateBackgroundUrl = :certificateBackgroundUrl,
                     certificateLogoUrl = :certificateLogoUrl,
                     certificateIssuer = :certificateIssuer,
                     certificateTitle = :certificateTitle,
                     certificateSubtitle = :certificateSubtitle,
                     certificateSignerName = :certificateSignerName,
                     certificateSignatureData = :certificateSignatureData
                 WHERE courseId = :courseId'
            );
            $req->execute([
                'courseId'    => $id,
                'title'       => $courses->getTitle(),
                'description' => $courses->getDescription(),
                'category'    => $courses->getCategorie(),
                'skill'       => $courses->getSkill(),
                'difficulty'  => $courses->getDifficulty(),
                'duration'    => $courses->getDuration(),
                'status'      => $courses->getStatut(),
                'createdAt'   => $courses->getPublished_AT()->format('Y-m-d'),
                'certificateTemplateType' => $courses->getCertificateTemplateType() ?: 'preset',
                'certificatePresetKey' => $courses->getCertificatePresetKey() ?: 'nvidia',
                'certificateBackgroundUrl' => $courses->getCertificateBackgroundUrl(),
                'certificateLogoUrl' => $courses->getCertificateLogoUrl(),
                'certificateIssuer' => $courses->getCertificateIssuer() ?: 'CareerStrand',
                'certificateTitle' => $courses->getCertificateTitle() ?: 'Certificate of Completion',
                'certificateSubtitle' => $courses->getCertificateSubtitle() ?: 'This certifies that',
                'certificateSignerName' => $courses->getCertificateSignerName() ?: 'Course Manager',
                'certificateSignatureData' => $courses->getCertificateSignatureData(),
            ]);
        } catch(Exception $e) {
            die('Erreur: ' . $e->getMessage());
        }
    }

    public function getCourseById($id) {
        $db = config::getConnexion();
        $this->ensureCertificateSchema($db);
        $query = $db->prepare($this->courseSelectSql() . ' WHERE courseId = ?');
        $query->execute([$id]);
        return $query->fetch();
    }

    public function getLastInsertedId() {
        $db = config::getConnexion();
        $this->ensureCertificateSchema($db);
        if ($this->lastInsertedId !== null) {
            return $this->lastInsertedId;
        }

        $lastId = (int)$db->lastInsertId();
        if ($lastId > 0) {
            return $lastId;
        }

        return (int)$db->query('SELECT COALESCE(MAX(courseId), 0) FROM course')->fetchColumn();
    }
}
?>
