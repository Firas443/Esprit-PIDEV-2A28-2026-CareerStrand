<?php
include_once 'C:/xampp/htdocs/careerstrand/config.php';

class ControlCourseVideos {

    private $uploadDir = 'C:/xampp/htdocs/Careerstrand/uploads/videos/';
    private $uploadUrl = 'uploads/videos/';

    //  Liste tt les vid
    // Si vide, retombe sur la colonne upload
    public function getVideosByCourse($courseId) {
        $db  = config::getConnexion();

        // Cherche dans course_videos
        $req = $db->prepare('SELECT * FROM course_videos WHERE course_id = ? ORDER BY position ASC');
        $req->execute([$courseId]);
        $videos = $req->fetchAll(PDO::FETCH_ASSOC);

        // 2. Si vide, retombe sur upload_video de la table course
        if (empty($videos)) {
            $req2 = $db->prepare('SELECT upload_video, Title FROM course WHERE CourseID = ?');
            $req2->execute([$courseId]);
            $row = $req2->fetch(PDO::FETCH_ASSOC);

            if ($row && !empty($row['upload_video'])) {
                // Nettoie chemin preced
                $path = $row['upload_video'];
                $path = ltrim($path, '/');
                if (stripos($path, 'careerstrand/') === 0) {
                    $path = substr($path, strlen('careerstrand/'));
                }

                $videos = [[
                    'video_id'   => 0,
                    'course_id'  => $courseId,
                    'title'      => !empty($row['Title']) ? $row['Title'] : 'Introduction',
                    'video_path' => $path,
                    'position'   => 1,
                ]];
            }
        }

        return $videos;
    }

    //  ajout nouv vid 
    public function addVideo($courseId, $title, $fileInput) {
        if (empty($_FILES[$fileInput]['name'])) return false;

        $file    = $_FILES[$fileInput];
        $allowed = ['video/mp4','video/webm','video/ogg'];
        $maxSize = 200 * 1024 * 1024; // 200 MB

        if (!in_array($file['type'], $allowed))  die('Format non supporté.');
        if ($file['size'] > $maxSize)            die('Fichier trop volumineux (max 200MB).');
        if ($file['error'] !== UPLOAD_ERR_OK)    die('Erreur upload : code ' . $file['error']);

        if (!is_dir($this->uploadDir)) mkdir($this->uploadDir, 0755, true);

        $db = config::getConnexion();

        //  si vid vide et upload vid existe
        $check = $db->prepare('SELECT COUNT(*) FROM course_videos WHERE course_id = ?');
        $check->execute([$courseId]);
        $count = (int)$check->fetchColumn();

        if ($count === 0) {
            $req2 = $db->prepare('SELECT upload_video, Title FROM course WHERE CourseID = ?');
            $req2->execute([$courseId]);
            $row = $req2->fetch(PDO::FETCH_ASSOC);

            if ($row && !empty($row['upload_video'])) {
                $path = $row['upload_video'];
                $path = ltrim($path, '/');
                if (stripos($path, 'careerstrand/') === 0) {
                    $path = substr($path, strlen('careerstrand/'));
                }
                // vid existante en pos 1
                $ins = $db->prepare('INSERT INTO course_videos (course_id, title, video_path, position) VALUES (?, ?, ?, 1)');
                $ins->execute([$courseId, $row['Title'] ?: 'Introduction', $path]);
            }
        }

        // Upload nouv fichier 
        $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('vid_', true) . '.' . $ext;
        $dest     = $this->uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) die('Impossible de déplacer le fichier.');

        // Pos max existant 
        $pos = $db->prepare('SELECT COALESCE(MAX(position),0)+1 FROM course_videos WHERE course_id = ?');
        $pos->execute([$courseId]);
        $position = (int)$pos->fetchColumn();

        $req = $db->prepare('INSERT INTO course_videos (course_id, title, video_path, position) VALUES (?, ?, ?, ?)');
        $req->execute([$courseId, $title, $this->uploadUrl . $filename, $position]);
        return true;
    }

    // Supp vid
    public function deleteVideo($videoId) {
        $db  = config::getConnexion();
        $req = $db->prepare('SELECT video_path FROM course_videos WHERE video_id = ?');
        $req->execute([$videoId]);
        $row = $req->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $path = 'C:/xampp/htdocs/Careerstrand/' . $row['video_path'];
            if (file_exists($path)) unlink($path);
            $del = $db->prepare('DELETE FROM course_videos WHERE video_id = ?');
            $del->execute([$videoId]);
        }
    }

    // vid visible front
    public function getVideosJson($courseId) {
        $videos = $this->getVideosByCourse($courseId);
        return json_encode($videos);
    }
}
?>
