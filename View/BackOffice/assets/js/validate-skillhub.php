<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../Controller/SkillHubCoreController.php';

$coreController = new SkillHubCoreController();
$type = $_GET['type'] ?? '';

$response = [
    'exists' => false,
    'message' => '',
];

if ($type === 'hub_name') {
    $name = trim((string) ($_GET['name'] ?? ''));
    $excludeGroupId = isset($_GET['excludeGroupId']) && $_GET['excludeGroupId'] !== ''
        ? (int) $_GET['excludeGroupId']
        : null;

    if ($name !== '') {
        $exists = $coreController->skillHubNameExists($name, $excludeGroupId);
        $response['exists'] = $exists;
        $response['message'] = $exists ? 'This hub name already exists.' : '';
    }
} elseif ($type === 'challenge_title') {
    $title = trim((string) ($_GET['title'] ?? ''));
    $groupId = isset($_GET['groupId']) ? (int) $_GET['groupId'] : 0;
    $excludeChallengeId = isset($_GET['excludeChallengeId']) && $_GET['excludeChallengeId'] !== ''
        ? (int) $_GET['excludeChallengeId']
        : null;

    if ($title !== '' && $groupId > 0) {
        $exists = $coreController->challengeTitleExists($title, $groupId, $excludeChallengeId);
        $response['exists'] = $exists;
        $response['message'] = $exists ? 'This challenge title already exists in the selected hub.' : '';
    }
}

echo json_encode($response);
