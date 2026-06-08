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
    private $certificateTemplateType;
    private $certificatePresetKey;
    private $certificateBackgroundUrl;
    private $certificateLogoUrl;
    private $certificateIssuer;
    private $certificateTitle;
    private $certificateSubtitle;
    private $certificateSignerName;
    private $certificateSignatureData;

    public function __construct(
        $Title,
        $Description,
        $Categorie,
        $Skill,
        $Difficulty,
        $Duration,
        $Statut,
        $Published_AT,
        $upload_video = null,
        $certificateTemplateType = 'preset',
        $certificatePresetKey = 'nvidia',
        $certificateBackgroundUrl = null,
        $certificateLogoUrl = null,
        $certificateIssuer = 'CareerStrand',
        $certificateTitle = 'Certificate of Completion',
        $certificateSubtitle = 'This certifies that',
        $certificateSignerName = 'Course Manager',
        $certificateSignatureData = null
    ) {
        $this->Title = $Title;
        $this->Description = $Description;
        $this->Categorie = $Categorie;
        $this->Skill = $Skill;
        $this->Difficulty = $Difficulty;
        $this->Duration = $Duration;
        $this->Statut = $Statut;
        $this->Published_AT = $Published_AT;
        $this->upload_video = $upload_video;
        $this->certificateTemplateType = $certificateTemplateType;
        $this->certificatePresetKey = $certificatePresetKey;
        $this->certificateBackgroundUrl = $certificateBackgroundUrl;
        $this->certificateLogoUrl = $certificateLogoUrl;
        $this->certificateIssuer = $certificateIssuer;
        $this->certificateTitle = $certificateTitle;
        $this->certificateSubtitle = $certificateSubtitle;
        $this->certificateSignerName = $certificateSignerName;
        $this->certificateSignatureData = $certificateSignatureData;
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
    public function getCertificateTemplateType() { return $this->certificateTemplateType; }
    public function getCertificatePresetKey()    { return $this->certificatePresetKey; }
    public function getCertificateBackgroundUrl() { return $this->certificateBackgroundUrl; }
    public function getCertificateLogoUrl()       { return $this->certificateLogoUrl; }
    public function getCertificateIssuer()        { return $this->certificateIssuer; }
    public function getCertificateTitle()         { return $this->certificateTitle; }
    public function getCertificateSubtitle()      { return $this->certificateSubtitle; }
    public function getCertificateSignerName()    { return $this->certificateSignerName; }
    public function getCertificateSignatureData() { return $this->certificateSignatureData; }

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
    public function setCertificateTemplateType($value) { $this->certificateTemplateType = $value; }
    public function setCertificatePresetKey($value)    { $this->certificatePresetKey = $value; }
    public function setCertificateBackgroundUrl($value) { $this->certificateBackgroundUrl = $value; }
    public function setCertificateLogoUrl($value)       { $this->certificateLogoUrl = $value; }
    public function setCertificateIssuer($value)        { $this->certificateIssuer = $value; }
    public function setCertificateTitle($value)         { $this->certificateTitle = $value; }
    public function setCertificateSubtitle($value)      { $this->certificateSubtitle = $value; }
    public function setCertificateSignerName($value)    { $this->certificateSignerName = $value; }
    public function setCertificateSignatureData($value) { $this->certificateSignatureData = $value; }
}
?>
