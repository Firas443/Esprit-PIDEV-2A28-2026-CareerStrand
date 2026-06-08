<?php
require_once 'c:/xampp/htdocs/Careerstrand/config.php';
require_once 'c:/xampp/htdocs/Careerstrand/utils/OpportunityInterviewService.php';

$pdo = config::getConnexion();
$stmt = $pdo->query("SELECT o.opportunityId, o.title, o.description, o.type, o.category, o.requiredLevel, o.status FROM Opportunity o WHERE o.status='published' LIMIT 1");
$opp = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$opp) { die("No published opportunity found.\n"); }

echo "Opportunity: " . $opp['title'] . "\n";
echo "Status: " . $opp['status'] . "\n";
echo "Desc length: " . strlen($opp['description'] ?? '') . "\n";

$skillStmt = $pdo->prepare("SELECT skillName, requiredLevel, isPrimary FROM opportunity_skill WHERE opportunityId = ?");
$skillStmt->execute([$opp['opportunityId']]);
$opp['skills'] = $skillStmt->fetchAll(PDO::FETCH_ASSOC);
echo "Skills: " . implode(', ', array_column($opp['skills'], 'skillName')) . "\n\n";

$service = new OpportunityInterviewService();
$result = $service->generateTechnicalQuestions($opp, ['level'=>'Starter','careerGoal'=>'','bio'=>'','location'=>'','preferences'=>''], []);

echo "Source: " . $result['source'] . "\n";
echo "Questions:\n";
foreach ($result['questions'] as $i => $q) {
    echo "  Q" . ($i+1) . ": $q\n";
}
