<?php
require_once __DIR__ . '/../../Controller/SkillHubController.php';

$skillHubController = new SkillHubController();
$hubs = $skillHubController->afficherHubs();
$selectedHubId = null;
$list = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['groupId'], $_POST['search'])) {
    $selectedHubId = (int) $_POST['groupId'];
    $list = $skillHubController->afficherWorkItems($selectedHubId);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recherche des tâches et projets par hub</title>
    <style>
        :root {
            --bg: #050816;
            --panel: rgba(10, 16, 34, 0.9);
            --border: rgba(255, 255, 255, 0.08);
            --text: #f5f3ee;
            --muted: rgba(245, 243, 238, 0.72);
            --blue: #6f8fd8;
            --red: #ff6e45;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: Inter, Arial, Helvetica, sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at 14% 18%, rgba(111, 143, 216, 0.12), transparent 24%),
                radial-gradient(circle at 84% 16%, rgba(255, 110, 69, 0.1), transparent 24%),
                linear-gradient(120deg, #030712 0%, #071126 46%, #090514 100%);
        }
        .page {
            width: min(1040px, calc(100% - 32px));
            margin: 0 auto;
            padding: 32px 0 56px;
            display: grid;
            gap: 18px;
        }
        .card {
            padding: 24px;
            border-radius: 24px;
            background: var(--panel);
            border: 1px solid var(--border);
            box-shadow: 0 18px 42px rgba(0, 0, 0, 0.24);
        }
        .eyebrow {
            margin: 0 0 10px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.16em;
            font-size: 12px;
        }
        h1, h2, h3 { margin: 0 0 12px; letter-spacing: -0.03em; }
        p, .muted { color: var(--muted); line-height: 1.6; }
        .search-form, .meta-row, .result-head {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .search-form { margin-top: 18px; }
        select, input[type="submit"] { font: inherit; }
        select {
            min-width: 240px;
            padding: 12px 14px;
            border-radius: 14px;
            border: 1px solid var(--border);
            background: rgba(255,255,255,0.04);
            color: var(--text);
        }
        .btn-main {
            padding: 12px 18px;
            border: none;
            border-radius: 999px;
            background: linear-gradient(90deg, var(--blue), var(--red));
            color: var(--text);
            font-weight: 700;
            cursor: pointer;
        }
        .result-list { display: grid; gap: 14px; margin-top: 18px; }
        .result-item {
            padding: 18px;
            border-radius: 18px;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.06);
        }
        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255, 110, 69, 0.12);
            color: var(--text);
            font-size: 12px;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <main class="page">
        <section class="card">
            <p class="eyebrow">Workshop Jointure</p>
            <h1>Recherche des tâches et projets par hub</h1>
            <p>Select a hub to display its related tasks and projects.</p>

            <form method="POST" class="search-form">
                <label for="groupId">Sélectionnez un hub :</label>
                <select name="groupId" id="groupId">
                    <?php foreach ($hubs as $hub) { ?>
                        <option value="<?= $hub['groupId']; ?>" <?= ($selectedHubId === (int) $hub['groupId']) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($hub['name']); ?>
                        </option>
                    <?php } ?>
                </select>
                <input type="submit" value="Rechercher" name="search" class="btn-main">
            </form>
        </section>

        <?php if (!empty($list)) { ?>
            <section class="card">
                <h2>Éléments correspondants au hub sélectionné :</h2>
                <div class="result-list">
                    <?php foreach ($list as $item) { ?>
                        <article class="result-item">
                            <div class="result-head">
                                <span class="badge"><?= htmlspecialchars(ucfirst($item['type'])); ?></span>
                                <span class="muted"><?= htmlspecialchars($item['hubName']); ?> / <?= htmlspecialchars($item['hubCategory']); ?></span>
                            </div>
                            <h3><?= htmlspecialchars($item['title']); ?></h3>
                            <p><?= htmlspecialchars($item['description']); ?></p>
                            <div class="meta-row">
                                <span class="muted">Difficulté : <?= htmlspecialchars($item['difficulty']); ?></span>
                                <span class="muted">Deadline : <?= htmlspecialchars((string) $item['deadline']); ?></span>
                                <span class="muted">Status : <?= htmlspecialchars($item['status']); ?></span>
                            </div>
                        </article>
                    <?php } ?>
                </div>
            </section>
        <?php } elseif ($selectedHubId !== null) { ?>
            <section class="card">
                <h2>Éléments correspondants au hub sélectionné :</h2>
                <p class="muted">No tasks or projects were found for this hub.</p>
            </section>
        <?php } ?>
    </main>
</body>
</html>
