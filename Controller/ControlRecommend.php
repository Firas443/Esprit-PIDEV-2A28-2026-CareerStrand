<?php
/*
 * C:/xampp/htdocs/Careerstrand/Controller/ControlRecommend.php
 * Réécriture complète — version minimale sans risque d'erreur
 */

// autorisat reque
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

// requete preflight CORS
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Seule méthode acceptée
if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array('success' => false, 'error' => 'Method not allowed'));
    exit;
}

// Lit le body
$raw = file_get_contents('php://input');
if (empty($raw)) {
    http_response_code(400);
    echo json_encode(array('success' => false, 'error' => 'Body vide'));
    exit;
}

// Décode le JSON
$input = json_decode($raw, true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(array('success' => false, 'error' => 'JSON invalide'));
    exit;
}

// Récup champs cours
$title       = isset($input['title'])       ? trim($input['title'])       : '';
$description = isset($input['description']) ? trim($input['description']) : '';
$category    = isset($input['category'])    ? trim($input['category'])    : '';
$skill       = isset($input['skill'])       ? trim($input['skill'])       : '';

if (empty($title) && empty($category) && empty($description)) {
    http_response_code(400);
    echo json_encode(array('success' => false, 'error' => 'Aucune donnee de cours recue'));
    exit;
}

// Construit requ recher 
$queries = array();

if (!empty($title)) {
    $queries[] = $title . ' tutorial';
}
if (!empty($category) && !empty($title)) {
    $queries[] = $category . ' ' . $title;
}
if (!empty($skill) && !empty($title)) {
    $queries[] = $skill . ' ' . $title;
}
if (empty($queries)) {
    $queries[] = (!empty($category) ? $category : 'programming') . ' tutorial';
}

// Clé YouTube API 
// APIs & Services 
$youtubeApiKey = 'VOTRE_CLE_YOUTUBE_API_V3';

//  Recherche YT
$allVideos = array();
$seenIds   = array();

$modeDemo = (empty($youtubeApiKey) || $youtubeApiKey === 'VOTRE_CLE_YOUTUBE_API_V3');

if ($modeDemo) {
    // Mode démo : vid selon sjt
    $pool = array(
        array('id' => 'rfscVS0vtbw', 'title' => 'Learn Python Full Course'),
        array('id' => 'zOjov-2OZ0E', 'title' => 'JavaScript Tutorial for Beginners'),
        array('id' => 'pTB0EiLXUC8', 'title' => 'CSS Tutorial Zero to Hero'),
        array('id' => 'yfoY53QXEnI', 'title' => 'C# Tutorial For Beginners'),
        array('id' => 'GhQdlIFylQ8', 'title' => 'Java Tutorial for Beginners'),
        array('id' => 'Ke90Tje7VS0', 'title' => 'React JS Full Course'),
        array('id' => 'WGJJIrtnfpk', 'title' => 'Arduino Tutorial for Beginners'),
        array('id' => 'CvUiX7d1hXc', 'title' => 'Electronics for Beginners'),
        array('id' => 'l9AzO1FMgM8', 'title' => 'Data Structures Full Course'),
        array('id' => 'vLnPwxZdW4Y', 'title' => 'C++ Tutorial for Beginners'),
    );

    // Hash basé sur le titre pour varier selon le cours
    $offset = abs(crc32($title . $category)) % count($pool);

    for ($i = 0; $i < 4; $i++) {
        $v = $pool[($offset + $i) % count($pool)];
        if (!in_array($v['id'], $seenIds)) {
            $seenIds[]   = $v['id'];
            $allVideos[] = array(
                'title'        => $queries[0] . ' — ' . $v['title'],
                'videoId'      => $v['id'],
                'thumbnail'    => 'https://img.youtube.com/vi/' . $v['id'] . '/mqdefault.jpg',
                'channelTitle' => 'MODE DEMO — Ajoutez votre cle YouTube API dans ControlRecommend.php',
            );
        }
    }

} else {
    //  recherche YT  API 
    if (function_exists('curl_init')) {
        foreach ($queries as $query) {
            if (count($allVideos) >= 6) {
                break;
            }

            $params = array(
                'part'       => 'snippet',
                'type'       => 'video',
                'maxResults' => 2,
                'q'          => $query,
                'key'        => $youtubeApiKey,
                'order'      => 'relevance',
            );

            $url = 'https://www.googleapis.com/youtube/v3/search?' . http_build_query($params);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL,            $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT,        10);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200 || empty($response)) {
                continue;
            }

            $data = json_decode($response, true);
            if (!is_array($data) || empty($data['items'])) {
                continue;
            }

            foreach ($data['items'] as $item) {
                $videoId = isset($item['id']['videoId']) ? $item['id']['videoId'] : '';
                if (empty($videoId) || in_array($videoId, $seenIds)) {
                    continue;
                }

                $sn  = isset($item['snippet']) ? $item['snippet'] : array();
                $th  = isset($sn['thumbnails']) ? $sn['thumbnails'] : array();

                if (!empty($th['high']['url'])) {
                    $thumb = $th['high']['url'];
                } elseif (!empty($th['medium']['url'])) {
                    $thumb = $th['medium']['url'];
                } else {
                    $thumb = 'https://img.youtube.com/vi/' . $videoId . '/mqdefault.jpg';
                }

                $seenIds[]   = $videoId;
                $allVideos[] = array(
                    'title'        => isset($sn['title'])        ? $sn['title']        : 'Sans titre',
                    'videoId'      => $videoId,
                    'thumbnail'    => $thumb,
                    'channelTitle' => isset($sn['channelTitle']) ? $sn['channelTitle'] : '',
                );
            }
        }
    }

    // Si YT n a rien, utilise le mode démo en fallback
    if (empty($allVideos)) {
        $pool = array(
            array('id' => 'rfscVS0vtbw', 'title' => 'Learn Programming Full Course'),
            array('id' => 'WGJJIrtnfpk', 'title' => 'Electronics Tutorial'),
        );
        foreach ($pool as $v) {
            $allVideos[] = array(
                'title'        => $title . ' — ' . $v['title'],
                'videoId'      => $v['id'],
                'thumbnail'    => 'https://img.youtube.com/vi/' . $v['id'] . '/mqdefault.jpg',
                'channelTitle' => 'Fallback (API YouTube sans résultats)',
            );
        }
    }
}

// Réponse finale
echo json_encode(array(
    'success'  => true,
    'query'    => isset($queries[0]) ? $queries[0] : $title,
    'keywords' => $queries,
    'videos'   => array_slice($allVideos, 0, 6),
));
exit;
?>