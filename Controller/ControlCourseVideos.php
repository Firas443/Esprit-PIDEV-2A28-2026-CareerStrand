<?php
require_once __DIR__ . '/../config.php';

class ControlCourseVideos {
    private $uploadDir = __DIR__ . '/../uploads/videos/';
    private $uploadUrl = 'uploads/videos/';

    private function ensureVideoTable(): void {
        $db = config::getConnexion();
        $db->exec(
            "CREATE TABLE IF NOT EXISTS course_videos (
                video_id INT(11) NOT NULL AUTO_INCREMENT,
                course_id INT(11) NOT NULL,
                title VARCHAR(255) DEFAULT NULL,
                video_path VARCHAR(255) NOT NULL,
                position INT(11) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (video_id),
                KEY idx_course_videos_course (course_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );
    }

    public function getVideosByCourse($courseId) {
        $this->ensureVideoTable();
        $db = config::getConnexion();

        $req = $db->prepare('SELECT * FROM course_videos WHERE course_id = ? ORDER BY position ASC, video_id ASC');
        $req->execute([$courseId]);
        return $req->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addVideo($courseId, $title, $fileInput) {
        $this->ensureVideoTable();
        if (empty($_FILES[$fileInput]['name'])) {
            return false;
        }

        $file = $_FILES[$fileInput];
        $allowed = ['video/mp4', 'video/webm', 'video/ogg'];
        $maxSize = 200 * 1024 * 1024;

        if (!in_array($file['type'], $allowed)) {
            die('Format non supporte.');
        }
        if ($file['size'] > $maxSize) {
            die('Fichier trop volumineux (max 200MB).');
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            die('Erreur upload : code ' . $file['error']);
        }

        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('vid_', true) . '.' . $ext;
        $dest = $this->uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            die('Impossible de deplacer le fichier.');
        }

        $db = config::getConnexion();
        $pos = $db->prepare('SELECT COALESCE(MAX(position), 0) + 1 FROM course_videos WHERE course_id = ?');
        $pos->execute([$courseId]);
        $position = (int)$pos->fetchColumn();

        $req = $db->prepare('INSERT INTO course_videos (course_id, title, video_path, position) VALUES (?, ?, ?, ?)');
        $req->execute([$courseId, $title, $this->uploadUrl . $filename, $position]);
        return true;
    }

    public function deleteVideo($videoId) {
        $this->ensureVideoTable();
        $db = config::getConnexion();
        $req = $db->prepare('SELECT video_path FROM course_videos WHERE video_id = ?');
        $req->execute([$videoId]);
        $row = $req->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $relativePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($row['video_path'], '/\\'));
            $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . $relativePath;
            if (file_exists($path)) {
                unlink($path);
            }
            $del = $db->prepare('DELETE FROM course_videos WHERE video_id = ?');
            $del->execute([$videoId]);
        }
    }

    public function getVideosJson($courseId) {
        return json_encode($this->getVideosByCourse($courseId));
    }
}
?>
