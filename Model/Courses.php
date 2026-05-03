<?php
class Courses {
    private $CourseID;
    private $Title;
    private $Description;
    private $Categorie;
    private $Skill;
    private $Difficulty;
    private $Duration;
    private $Statut;
    private $Published_AT;
    private $upload_video;

    public function __construct($Title, $Description, $Categorie, $Skill, $Difficulty, $Duration, $Statut, $Published_AT, $upload_video = null) {
        $this->Title = $Title;
        $this->Description = $Description;
        $this->Categorie = $Categorie;
        $this->Skill = $Skill;
        $this->Difficulty = $Difficulty;
        $this->Duration = $Duration;
        $this->Statut = $Statut;
        $this->Published_AT = $Published_AT;
        $this->upload_video = $upload_video;
    }

    // Getters
    public function getCourseID()        { return $this->CourseID; }
    public function getTitle()           { return $this->Title; }
    public function getDescription()     { return $this->Description; }
    public function getCategorie()       { return $this->Categorie; }
    public function getSkill()           { return $this->Skill; }
    public function getDifficulty()      { return $this->Difficulty; }
    public function getDuration()        { return $this->Duration; }
    public function getStatut()          { return $this->Statut; }
    public function getPublished_AT()    { return $this->Published_AT; }
    public function getUploadVideo()     { return $this->upload_video; }

    // Setters
    public function setCourseID($id)               { $this->CourseID = $id; }
    public function setTitle($title)               { $this->Title = $title; }
    public function setDescription($desc)          { $this->Description = $desc; }
    public function setCategorie($cat)             { $this->Categorie = $cat; }
    public function setSkill($skill)               { $this->Skill = $skill; }
    public function setDifficulty($diff)           { $this->Difficulty = $diff; }
    public function setDuration($dur)              { $this->Duration = $dur; }
    public function setStatut($stat)               { $this->Statut = $stat; }
    public function setPublished_AT($date)         { $this->Published_AT = $date; }
    public function setUploadVideo($video)         { $this->upload_video = $video; }
}
?>