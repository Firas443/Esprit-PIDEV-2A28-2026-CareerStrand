<?php
require_once __DIR__ . '/../Model/calendar.php';
require_once __DIR__ . '/../config.php';

class controlcalendar {
    private function calendarSelectSql(): string {
        return "SELECT
                    cal.calendarId AS calendarID,
                    COALESCE(c.title, '') AS Title,
                    cal.startDate,
                    cal.endDate,
                    cal.progress AS Progress,
                    cal.status AS Status,
                    cal.courseId AS CourseID
                FROM calendar cal
                LEFT JOIN course c ON c.courseId = cal.courseId";
    }

    private function nextCalendarId(PDO $db): int {
        return (int)$db->query('SELECT COALESCE(MAX(calendarId), 0) + 1 FROM calendar')->fetchColumn();
    }

    private function findCourseIdByTitle(PDO $db, string $title): ?int {
        $req = $db->prepare('SELECT courseId FROM course WHERE title = ? LIMIT 1');
        $req->execute([$title]);
        $courseId = $req->fetchColumn();
        return $courseId !== false ? (int)$courseId : null;
    }

    public function listecalendar() {
        $db = config::getConnexion();
        try {
            $liste = $db->query($this->calendarSelectSql() . ' ORDER BY cal.startDate ASC, cal.calendarId ASC');
            return $liste->fetchAll(PDO::FETCH_ASSOC);
        } catch(Exception $e) {
            die('Erreur: ' . $e->getMessage());
        }
    }

    public function getcalendarById($id) {
        $db = config::getConnexion();
        try {
            $req = $db->prepare($this->calendarSelectSql() . ' WHERE cal.calendarId = ?');
            $req->execute([$id]);
            return $req->fetch(PDO::FETCH_ASSOC);
        } catch(Exception $e) {
            die('Erreur: ' . $e->getMessage());
        }
    }

    public function addcalendar($calendar) {
        $db = config::getConnexion();
        try {
            $calendarId = $this->nextCalendarId($db);
            $courseId = $this->findCourseIdByTitle($db, $calendar->getTitle());
            $req = $db->prepare(
                'INSERT INTO calendar (calendarId, courseId, startDate, endDate, progress, status)
                 VALUES (:calendarId, :courseId, :startDate, :endDate, :progress, :status)'
            );
            $req->execute([
                'calendarId' => $calendarId,
                'courseId'   => $courseId,
                'startDate'  => $calendar->getstartDate()->format('Y-m-d'),
                'endDate'    => $calendar->getendDate()->format('Y-m-d'),
                'progress'   => $calendar->getProgress(),
                'status'     => $calendar->getStatus(),
            ]);
        } catch(Exception $e) {
            die('Erreur: ' . $e->getMessage());
        }
    }

    public function deletecalendar($id) {
        $db = config::getConnexion();
        try {
            $req = $db->prepare('DELETE FROM calendar WHERE calendarId = :calendarId');
            $req->execute(['calendarId' => $id]);
        } catch(Exception $e) {
            die('Erreur: ' . $e->getMessage());
        }
    }

    public function updatecalendar($calendar, $calendarID) {
        $db = config::getConnexion();
        try {
            $courseId = $this->findCourseIdByTitle($db, $calendar->getTitle());
            $req = $db->prepare(
                'UPDATE calendar
                 SET courseId = :courseId,
                     startDate = :startDate,
                     endDate = :endDate,
                     progress = :progress,
                     status = :status
                 WHERE calendarId = :calendarId'
            );
            $req->execute([
                'calendarId' => $calendarID,
                'courseId'   => $courseId,
                'startDate'  => $calendar->getstartDate()->format('Y-m-d'),
                'endDate'    => $calendar->getendDate()->format('Y-m-d'),
                'progress'   => $calendar->getProgress(),
                'status'     => $calendar->getStatus(),
            ]);
        } catch(Exception $e) {
            die('Erreur: ' . $e->getMessage());
        }
    }
}
?>
