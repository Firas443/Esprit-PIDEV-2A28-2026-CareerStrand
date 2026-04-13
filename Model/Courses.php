<?php
    class Courses{
        private string $Title;
        private string $Description;
        private string $Categorie;
        private string $Skills;
        private string $Difficulty;
        private int $Duration;
        private string $Statut;
        private DateTime $CreatedAT;

        public function getTitle(){return $this->Title;}
        public function getDescription(){return $this->Description;}
        public function getCategorie(){return $this->Categorie;}
        public function getSkills(){return $this->Skills;}
        public function getDifficulty(){return $this->Difficulty;}
        public function getDuration(){return $this->Duration;}
        public function getStatut(){return $this->Statut;}
        public function getCreatedAT(){return $this->CreatedAT;}

        public function setTitle(string $Title){$this->Title=$Title;}
        public function setDescription(string $Description){$this->Description=$Description;}
        public function setCategorie(string $Categorie){$this->Categorie=$Categorie;}
        public function setSkills(string $Skills){$this->Skills=$Skills;}
        public function setDifficulty(string $Difficulty){$this->Difficulty=$Difficulty;}
        public function setDuration(int $Duration){$this->Duration=$Duration;}
        public function setStatus(string $Statut){$this->Statut=$Statut;}
        public function setCreatedAT(DateTime $CreatedAT){$this->CreatedAT=$CreatedAT;}

        public function __construct(string $Title, string $Description, string $Categorie, string $Skills, string $Difficulty, int $Duration, string $Statut, DateTime $CreatedAT){
            $this->Title=$Title;
            $this->Description=$Description;
            $this->Categorie=$Categorie;
            $this->Skills=$Skills;
            $this->Difficulty=$Difficulty;
            $this->Duration=$Duration;
            $this->Statut=$Statut;
            $this->CreatedAT=$CreatedAT;
        }
    }



?>