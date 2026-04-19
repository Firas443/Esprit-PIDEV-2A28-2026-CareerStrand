<?php
include "C:/xampp/htdocs/careerstrand/Model/calendar.php";
include_once "C:/xampp/htdocs/careerstrand/config.php";

class controlcalendar {

    public function listecalendar() {
        $db = config::getConnexion();
        try {
            $liste = $db->query('SELECT * FROM calendar');
            return $liste->fetchAll(PDO::FETCH_ASSOC);
        } catch(Exception $e) {
            die('Erreur: ' . $e->getMessage());
        }
    }

    public function addcalendar($calendar) {
        $db = config::getConnexion();
        try {
            $req = $db->prepare('INSERT INTO calendar VALUES (NULL, :t, :sd, :ed, :p, :s)');
            $req->execute([
                't'  => $calendar->getTitle(),
                'sd' => $calendar->getstartDate()->format('Y-m-d'),
                'ed' => $calendar->getendDate()->format('Y-m-d'),
                'p'  => $calendar->getProgress(),
                's'  => $calendar->getStatus()
            ]);
        } catch(Exception $e) {
            die('Erreur: ' . $e->getMessage());
        }
    }

    public function deletecalendar($id) {
        $db = config::getConnexion();
        try {
            $req = $db->prepare('DELETE FROM calendar WHERE calendarID = :calendarID');
            $req->execute(['calendarID' => $id]);
        } catch(Exception $e) {
            die('Erreur: ' . $e->getMessage());
        }
    }

    public function updatecalendar($calendar, $calendarID) {
        $db = config::getConnexion();
        try {
            $req = $db->prepare('UPDATE calendar SET Title=:Title, startDate=:startDate, endDate=:endDate, Progress=:Progress, Status=:Status WHERE calendarID=:calendarID');
            $req->execute([
                'calendarID' => $calendarID,
                'Title'      => $calendar->getTitle(),
                'startDate'  => $calendar->getstartDate()->format('Y-m-d'),
                'endDate'    => $calendar->getendDate()->format('Y-m-d'),
                'Progress'   => $calendar->getProgress(),
                'Status'     => $calendar->getStatus()
            ]);
        } catch(Exception $e) {
            die('Erreur: ' . $e->getMessage());
        }
    }
}
?>