<?php
include 'C:/xampp/htdocs/Careerstrand/config.php';
include 'C:/xampp/htdocs/Careerstrand/Model/Courses.php';
    class ControlCourses{
        public function listeCourse(){
            $db=config::getConnexion();
            try{
                $liste=$db->query('SELECT * FROM course');
                return $liste;
            }catch(Exception $e){
                die('Erreur:'.$e->getMessage());
            }
        }
        public function addCourse($courses){
            $db=config::getConnexion();
            try{
                $req=$db->prepare('INSERT INTO course VALUES (NULL, :t, :d, :c, :s, :di, :du, :st, :a)');
                $req->execute([
                    't' => $courses->getTitle(),
                    'd' => $courses->getDescription(),
                    'c' => $courses->getCategorie(),
                    's' => $courses->getSkills(),
                    'di' => $courses->getDifficulty(),
                    'du' => $courses->getDuration(),
                    'st' => $courses->getStatut(),
                    'a' => $courses->getCreatedAT()->format('Y-m-d'), 
                ]);
            }catch(Exception $e){
                die('Erreur:'.$e->getMessage());
            }
        }
        public function deleteCourse($id){
            $db = config::getConnexion();
            try{
                $req = $db->prepare('
                DELETE FROM course where CourseID=:CourseID
                ');
                $req->execute([
                    'CourseID'=>$id
                ]);
            } catch (Exception $e) {
                die('Erreur: '.$e->getMessage());
            }
        }
        public function updateCourse($courses,$id){
            $db = config::getConnexion();
            try{
                $req = $db->prepare('UPDATE course SET Title=:Title, Description=:Description, Categorie=:Categorie, Skill=:Skill, Difficulty=:Difficulty, Duration=:Duration, Statut=:Statut, CreatedAT=:CreatedAT WHERE CourseID=:CourseID');
                $req->execute([
                    'CourseID'=>$id,
                    'Title'=>$courses->getTitle(),
                    'Description'=>$courses->getDescription(),
                    'Categorie'=>$courses->getCategorie(),
                    'Skill' =>$courses->getSkills(),
                    'Difficulty' =>$courses->getDifficulty(),
                    'Duration' =>$courses->getDuration(),
                    'Statut' =>$courses->getStatut(),
                    'CreatedAT' =>$courses->getCreatedAT()->format('Y-m-d'),
                ]);
            } catch (Exception $e) {
                die('Erreur: '.$e->getMessage());
            }
        }
    }

?>