<?php

require_once __DIR__ . '/../Model/UserQuestionnaire.php';
require_once __DIR__ . '/../Controller/ProfileController.php';

class QuestionnaireController
{
    private UserQuestionnaire $model;
    private ProfileController $profileController;

    public function __construct()
    {
        $this->model = new UserQuestionnaire();
        $this->profileController = new ProfileController();
    }

    public function saveAnswers(int $userId, array $answers): void
    {
        $this->model->deleteAnswersByUser($userId);

        $labels = [
            'field'      => 'Main field',
            'experience' => 'Experience level',
            'skills'     => 'Strongest skills',
            'workStyle'  => 'Work style',
            'goal'       => 'Career goal',
            'aiAnswer1'  => $answers['aiQuestion1'] ?? 'AI question 1',
            'aiAnswer2'  => $answers['aiQuestion2'] ?? 'AI question 2',
            'aiAnswer3'  => $answers['aiQuestion3'] ?? 'AI question 3',
        ];

        foreach ($answers as $key => $answer) {
            if (in_array($key, ['aiQuestion1', 'aiQuestion2', 'aiQuestion3'], true)) {
                continue;
            }

            $question = $labels[$key] ?? $key;
            $answer = trim((string)$answer);

            if ($answer !== '') {
                $this->model->saveAnswer($userId, $question, $answer);
            }
        }
    }

    public function generateBio(array $answers, string $role = 'user', string $fullName = 'This member'): string
    {
        $name = trim($fullName) !== '' ? trim($fullName) : 'This member';
        $firstName = explode(' ', $name)[0] ?? $name;

        $field      = trim($answers['field'] ?? 'their chosen field');
        $experience = trim($answers['experience'] ?? 'motivated');
        $skills     = trim($answers['skills'] ?? 'continuous learning');
        $workStyle  = trim($answers['workStyle'] ?? 'a professional work style');
        $goal       = trim($answers['goal'] ?? 'grow professionally');
        $ai1        = trim($answers['aiAnswer1'] ?? '');
        $ai2        = trim($answers['aiAnswer2'] ?? '');
        $ai3        = trim($answers['aiAnswer3'] ?? '');
        $insights   = trim($ai1 . ' ' . $ai2 . ' ' . $ai3);

        if ($role === 'manager recruiter') {
            return trim(
                "{$firstName} is a {$experience} recruiter focused on {$field}. " .
                "They represent a recruitment profile with strengths in {$skills}. " .
                "{$workStyle}, and their main objective is to {$goal}. " .
                ($insights !== '' ? "Their recruitment approach highlights: {$insights}" : '')
            );
        }

        if ($role === 'manager') {
            return trim(
                "{$firstName} is a {$experience} manager interested in {$field}. " .
                "They bring strengths in {$skills}. " .
                "{$workStyle}, and their goal is to {$goal}. " .
                ($insights !== '' ? "They also want to support their community by: {$insights}" : '')
            );
        }

        return trim(
            "{$firstName} is a {$experience} CareerStrand member interested in {$field}. " .
            "They have strengths in {$skills}. " .
            "{$workStyle}, and their career goal is to {$goal}. " .
            ($insights !== '' ? "Personal insights from their answers: {$insights}" : '')
        );
    }

    public function saveBio(int $userId, string $bio): array
    {
        $existing = $this->profileController->getByUserId($userId);

        return $this->profileController->createOrUpdate($userId, [
            'bio'         => $bio,
            'photoUrl'    => $existing ? $existing->getPhotoUrl() : '',
            'location'    => $existing ? $existing->getLocation() : '',
            'preferences' => $existing ? $existing->getPreferences() : '',
        ]);
    }
}
?>
