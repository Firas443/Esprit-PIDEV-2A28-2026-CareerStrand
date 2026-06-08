<?php
    class calendar{
        private string $Title;
        private DateTime $startDate;
        private DateTime $endDate;
        private int $Progress;
        private string $Status;
        
        public function getTitle(){return $this->Title;}
        public function getstartDate(){return $this->startDate;}
        public function getendDate(){return $this->endDate;}
        public function getProgress(){return $this->Progress;}
        public function getStatus(){return $this->Status;}        

        public function setTitle(string $Title){$this->Title=$Title;}
        public function setstartDate(DateTime $startDate){$this->startDate=$startDate;}
        public function setendDate(DateTime $endDate){$this->endDate=$endDate;}
        public function setProgress(int $Progress){$this->Progress=$Progress;}
        public function setStatus(string $Status){$this->Status=$Status;}
        
        public function __construct(string $Title,DateTime $startDate, DateTime $endDate, int $Progress, string $Status){
            $this->Title=$Title;
            $this->startDate=$startDate;
            $this->endDate=$endDate;
            $this->Progress=$Progress;
            $this->Status=$Status;
        }
    }

?>