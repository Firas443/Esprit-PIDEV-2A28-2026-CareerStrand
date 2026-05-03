<?php
include 'C:/xampp/htdocs/Careerstrand/config.php';
include 'C:/xampp/htdocs/Careerstrand/Model/Courses.php';

class ControlCourses {

    // ── Dossier où les vidéos sont stockées ──
    private $uploadDir = 'C:/xampp/htdocs/Careerstrand/uploads/videos/';
    private $uploadUrl = 'uploads/videos/';

    // ── Gère l'upload de la vidéo, retourne le chemin URL ou '' ──
    private function handleVideoUpload($currentVideo = '') {
        // Pas de fichier envoyé ou fichier vide → on garde l'ancienne valeur
        if (empty($_FILES['upload_video']['name'])) {
            return $currentVideo ?? '';
        }

        $file     = $_FILES['upload_video'];
        $allowed  = ['video/mp4', 'video/webm', 'video/ogg'];
        $maxSize  = 50 * 1024 * 1024; // 50 MB

        // Vérifications
        if (!in_array($file['type'], $allowed)) {
            die('Erreur : Format vidéo non supporté (MP4, WebM, OGG requis).');
        }
        if ($file['size'] > $maxSize) {
            die('Erreur : Fichier trop volumineux (max 50 MB).');
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            die('Erreur upload : code ' . $file['error']);
        }

        // Crée le dossier si nécessaire
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }

        // Nom unique pour éviter les conflits
        $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('video_', true) . '.' . $ext;
        $dest     = $this->uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            die('Erreur : Impossible de déplacer le fichier uploadé.');
        }

        return $this->uploadUrl . $filename;
    }

    public function listeCourse() {
        $db = config::getConnexion();
        try {
            $liste = $db->query('SELECT * FROM course');
            return $liste;
        } catch(Exception $e) {
            die('Erreur:' . $e->getMessage());
        }
    }

    public function addCourse($courses) {
        $db = config::getConnexion();
        try {
            // Gère l'upload vidéo (retourne '' si aucune vidéo)
            $videoPath = $this->handleVideoUpload('');

            $req = $db->prepare('INSERT INTO course VALUES (NULL, :t, :d, :c, :s, :di, :du, :st, :a, :v)');
            $req->execute([
                't'  => $courses->getTitle(),
                'd'  => $courses->getDescription(),
                'c'  => $courses->getCategorie(),
                's'  => $courses->getSkill(),
                'di' => $courses->getDifficulty(),
                'du' => $courses->getDuration(),
                'st' => $courses->getStatut(),
                'a'  => $courses->getPublished_AT()->format('Y-m-d'),
                'v'  => $videoPath,
            ]);
        } catch(Exception $e) {
            die('Erreur:' . $e->getMessage());
        }
    }

    public function deleteCourse($id) {
        $db = config::getConnexion();
        try {
            $req = $db->prepare('DELETE FROM course WHERE CourseID=:CourseID');
            $req->execute(['CourseID' => $id]);
        } catch(Exception $e) {
            die('Erreur: ' . $e->getMessage());
        }
    }

    public function updateCourse($courses, $id) {
        $db = config::getConnexion();
        try {
            // Récupère la vidéo actuelle pour la garder si aucune nouvelle n'est uploadée
            $existing = $this->getCourseById($id);
            $currentVideo = $existing['upload_video'] ?? '';

            $videoPath = $this->handleVideoUpload($currentVideo);

            $req = $db->prepare('UPDATE course SET Title=:Title, Description=:Description, Categorie=:Categorie, Skill=:Skill, Difficulty=:Difficulty, Duration=:Duration, Statut=:Statut, Published_AT=:Published_AT, upload_video=:upload_video WHERE CourseID=:CourseID');
            $req->execute([
                'CourseID'     => $id,
                'Title'        => $courses->getTitle(),
                'Description'  => $courses->getDescription(),
                'Categorie'    => $courses->getCategorie(),
                'Skill'        => $courses->getSkill(),
                'Difficulty'   => $courses->getDifficulty(),
                'Duration'     => $courses->getDuration(),
                'Statut'       => $courses->getStatut(),
                'Published_AT' => $courses->getPublished_AT()->format('Y-m-d'),
                'upload_video' => $videoPath,
            ]);
        } catch(Exception $e) {
            die('Erreur: ' . $e->getMessage());
        }
    }

    public function getCourseById($id) {
        $db    = config::getConnexion();
        $query = $db->prepare("SELECT * FROM course WHERE CourseID = ?");
        $query->execute([$id]);
        return $query->fetch();
    }
}
?>