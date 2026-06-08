<?php
// C:/xampp/htdocs/Careerstrand/Controller/debug_recommend.php
// Ouvre : http://localhost/careerstrand/Controller/debug_recommend.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/plain; charset=utf-8');

echo "=== DEBUG ControlRecommend ===\n\n";

// 1. Affiche le chemin réel du fichier
$file = __DIR__ . '/ControlRecommend.php';
echo "Chemin testé : $file\n";
echo "Fichier existe : " . (file_exists($file) ? "OUI" : "NON") . "\n\n";

// 2. Affiche le contenu brut des 10 dernières lignes (pour voir __FILE__ vs _FILE_)
if (file_exists($file)) {
    $lines = file($file);
    $total = count($lines);
    echo "Total lignes : $total\n";
    echo "--- 10 dernières lignes ---\n";
    $last = array_slice($lines, -10);
    foreach ($last as $i => $l) {
        echo ($total - 10 + $i + 1) . ": " . $l;
    }
    echo "\n";

    // 3. Cherche _FILE_ (bug connu) vs __FILE__
    $content = file_get_contents($file);
    if (preg_match('/[^_]_FILE_[^_]/', $content)) {
        echo "❌ BUG TROUVE : '_FILE_' (sans double underscore) détecté !\n";
    } else {
        echo "✅ __FILE__ correct (double underscore)\n";
    }

    // 4. Cherche d'autres erreurs courantes
    if (strpos($content, 'basename(__FILE__)') !== false) {
        echo "✅ basename(__FILE__) présent\n";
    }

    // 5. Vérifie la syntaxe en parsant le fichier
    echo "\n--- Tokens PHP suspects ---\n";
    $tokens = @token_get_all($content);
    $errors = [];
    foreach ($tokens as $tok) {
        if (is_array($tok) && $tok[0] === T_STRING && $tok[1] === '_FILE_') {
            $errors[] = "Ligne " . $tok[2] . ": constante invalide _FILE_";
        }
    }
    if (empty($errors)) {
        echo "Aucune constante invalide détectée via tokenizer\n";
    } else {
        foreach ($errors as $e) echo "❌ $e\n";
    }
}

echo "\n--- Test inclusion avec output buffering ---\n";
try {
    $_SERVER['REQUEST_METHOD'] = 'OPTIONS'; // pour ne pas exécuter recommend()
    ob_start();
    @include $file;
    $out = ob_get_clean();
    echo "Inclusion OK\n";
    if (!empty($out)) echo "Output capturé : " . $out . "\n";
    if (class_exists('ControlRecommend')) {
        echo "✅ Classe ControlRecommend existe\n";
    } else {
        echo "❌ Classe ControlRecommend ABSENTE après inclusion\n";
    }
} catch (Throwable $e) {
    ob_end_clean();
    echo "❌ Erreur inclusion : " . $e->getMessage() . "\n";
    echo "Fichier : " . $e->getFile() . " ligne " . $e->getLine() . "\n";
}

echo "\n--- Test appel POST simulé ---\n";
if (class_exists('ControlRecommend')) {
    try {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        // Simule le body JSON
        $mockInput = json_encode([
            'title'       => 'microcontroleur',
            'description' => 'Un microcontrôleur intégré',
            'category'    => 'Programming',
            'skill'       => 'Problem solving',
        ]);

        // Override php://input via stream wrapper
        // (pas possible directement, on appelle les méthodes privées via Reflection)
        $ctrl = new ControlRecommend();
        $ref  = new ReflectionClass($ctrl);

        $bsq = $ref->getMethod('buildSearchQueries');
        $bsq->setAccessible(true);
        $queries = $bsq->invoke($ctrl, 'microcontroleur', 'Un microcontrôleur intégré', 'Programming', 'Problem solving');
        echo "buildSearchQueries OK : " . json_encode($queries) . "\n";

        $syt = $ref->getMethod('searchYouTube');
        $syt->setAccessible(true);
        $videos = $syt->invoke($ctrl, $queries[0], 2);
        echo "searchYouTube OK : " . count($videos) . " vidéo(s)\n";
        echo json_encode($videos, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";

    } catch (Throwable $e) {
        echo "❌ Erreur : " . $e->getMessage() . " (ligne " . $e->getLine() . ")\n";
    }
}

echo "\n=== FIN DEBUG ===\n";
?>
