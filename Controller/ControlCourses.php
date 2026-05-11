<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../Model/Courses.php';

class ControlCourses {
    private $lastInsertedId = null;

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
                    NULL AS upload_video
                FROM course";
    }

    private function nextCourseId(PDO $db): int {
        return (int)$db->query('SELECT COALESCE(MAX(courseId), 0) + 1 FROM course')->fetchColumn();
    }

    public function listeCourse() {
        $db = config::getConnexion();
        try {
            return $db->query($this->courseSelectSql() . ' ORDER BY courseId DESC');
        } catch(Exception $e) {
            die('Erreur:' . $e->getMessage());
        }
    }

    public function addCourse($courses) {
        $db = config::getConnexion();
        try {
            $courseId = $this->nextCourseId($db);
            $req = $db->prepare(
                'INSERT INTO course (courseId, title, description, category, skill, difficulty, duration, status, createdAt)
                 VALUES (:courseId, :title, :description, :category, :skill, :difficulty, :duration, :status, :createdAt)'
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
            $req = $db->prepare(
                'UPDATE course
                 SET title = :title,
                     description = :description,
                     category = :category,
                     skill = :skill,
                     difficulty = :difficulty,
                     duration = :duration,
                     status = :status,
                     createdAt = :createdAt
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
            ]);
        } catch(Exception $e) {
            die('Erreur: ' . $e->getMessage());
        }
    }

    public function getCourseById($id) {
        $db = config::getConnexion();
        $query = $db->prepare($this->courseSelectSql() . ' WHERE courseId = ?');
        $query->execute([$id]);
        return $query->fetch();
    }

    public function getLastInsertedId() {
        if ($this->lastInsertedId !== null) {
            return $this->lastInsertedId;
        }

        $db = config::getConnexion();
        $lastId = (int)$db->lastInsertId();
        if ($lastId > 0) {
            return $lastId;
        }

        return (int)$db->query('SELECT COALESCE(MAX(courseId), 0) FROM course')->fetchColumn();
    }
}
?>
