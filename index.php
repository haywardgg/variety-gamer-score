<!DOCTYPE html>
<?php
require_once __DIR__ . '/lib/helpers.php';
$config = require __DIR__ . '/assets/config.php';
$gamerscoreDisplayCount = isset($config['gamerscore']['display_count'])
    ? (int) $config['gamerscore']['display_count']
    : 50;

$steamTopFile = __DIR__ . '/data/steam-top100.json';
$steamTopGames = $loadSteamTopGames($steamTopFile);
$maintenanceMessage = 'Steam game data is unavailable. Please try again later.';

$gameEntries = [];
foreach ($steamTopGames as $gameData) {
    if (!is_array($gameData)) {
        continue;
    }
    $name = trim((string) ($gameData['name'] ?? ''));
    if ($name === '') {
        continue;
    }
    $thumbnail = $buildThumbnailSources($gameData['thumbnail'] ?? null);
    $gameEntries[] = [
        'name' => $name,
        'thumbnail' => $thumbnail,
    ];
}

$totalGames = count($gameEntries);
$useMaxGameCount = isset($_GET['max']) && $_GET['max'] === 'true';
if ($useMaxGameCount) {
    $gamerscoreDisplayCount = $totalGames;
}
$showGames = $totalGames > 0;
?>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PCGamers.win - What's your VARIETY gamer score?</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="page">
        <header class="hero">
            <div class="hero__badges">
                <a class="hero__badge" href="https://pcgamers.win">
                    <i class="fa-solid fa-gamepad"></i>
                    <span class="hero__badge-text">PCGamers.win</span>
                </a>
                <a class="hero__badge" href="https://github.com/haywardgg" aria-label="GitHub">
                    <i class="fa-brands fa-github"></i>
                </a>
            </div>
            <h1>
                <span class="hero__title-long">What's your <span>VARIETY</span> gamer score?</span>
                <span class="hero__title-short"><span>VARIETY</span> gamer score.</span>
            </h1>
            <?php if (!$showGames): ?>
                <div class="download-notice">
                    <i class="fa-solid fa-screwdriver-wrench"></i>
                    <?php echo htmlspecialchars($maintenanceMessage, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>
        </header>

        <?php if ($showGames): ?>
            <section class="game-view" id="gameView">
                <div class="game-card">
                    <div class="game-card__progress" id="gameProgress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                        <div class="game-card__progress-track">
                            <div class="game-card__progress-fill" id="gameProgressBar"></div>
                        </div>
                    </div>
                    <picture class="game-card__thumb-frame" id="gameThumbnailPicture" hidden="">
                        <source id="gameThumbnailWebp" type="image/webp">
                        <img class="game-card__thumb" id="gameThumbnail" alt="" loading="lazy">
                    </picture>
                    <h2 class="game-card__name" id="gameName"></h2>
                    <p class="game-card__status" id="gameStatus">Mark your progress to keep score.</p>
                    <p class="game-card__share" id="shareText" hidden></p>
     
                <div class="controls__buttons" id="controlsButtons" hidden>
                    <button class="button" id="downloadBtn">
                        <i class="fa-solid fa-download"></i>
                        Export scorecard
                    </button>
                    <button class="button button--ghost" id="shareBtn">
                        <i class="fa-solid fa-copy"></i>
                        Copy share text
                    </button>
                    <button class="button button--secondary" id="resetBtn">
                        <i class="fa-solid fa-rotate"></i>
                        Start over
                    </button>
                </div>
   
                    
                </div>
                <div class="game-actions">
                    <button class="button button--ghost" id="prevBtn">
                        <i class="fa-solid fa-arrow-left"></i>
                        <span class="button__text button__text--hide-sm">Previous</span>
                    </button>
                    <button class="button button--secondary" id="notPlayedBtn">
                        <i class="fa-solid fa-thumbs-down"></i>
                        <span class="button__text">Not Played</span>
                    </button>
                    <button class="button" id="playedBtn">
                        <i class="fa-solid fa-thumbs-up"></i>
                        <span class="button__text">Played It</span>
                    </button>
                </div>
            </section>

            <section class="summary-lists" id="summaryLists" hidden>
                <div class="summary-lists__grid">
                    <div class="summary-list">
                        <h3 class="summary-list__title">Played</h3>
                        <ul class="summary-list__items" id="playedList"></ul>
                    </div>
                    <div class="summary-list">
                        <h3 class="summary-list__title">Not Played</h3>
                        <ul class="summary-list__items" id="notPlayedList"></ul>
                    </div>
                </div>
            </section>        
        
         <section class="summary-bar">
                <div class="summary-bar__grid">
                    <div class="summary-card">
                        <span class="summary-card__label">
                            <i class="fa-solid fa-thumbs-up"></i>
                            <span class="summary-card__label-text">Played</span>
                        </span>
                        <span class="summary-card__value" id="selectedCount">0</span>
                    </div>
                    <div class="summary-card">
                        <span class="summary-card__label">
                            <i class="fa-solid fa-thumbs-down"></i>
                            <span class="summary-card__label-text">Not Played</span>
                        </span>
                        <span class="summary-card__value" id="notPlayedCount">0</span>
                    </div>
                    <div class="summary-card">
                        <span class="summary-card__label">
                            <i class="fa-solid fa-gauge-high"></i>
                            <span class="summary-card__label-text">Score</span>
                        </span>
                        <span class="summary-card__value" id="scorePercent">0/0</span>
                    </div>
                </div>
            </section>    
            <div class="completion-counter">
                Completed: <span id="completionCount">0</span>
            </div>


        
        <?php else: ?>
            <section class="game-view">
                <div class="game-card">
                    <h2 class="game-card__name">Game data unavailable</h2>
                    <p class="game-card__status">We could not load the latest Steam game list.</p>
                </div>
            </section>
        <?php endif; ?>
    </div>

    <?php if ($showGames): ?>
        <script>
            window.VARIETY_DATA = <?php echo json_encode([
                'totalGames' => $totalGames,
                'games' => $gameEntries,
            ]); ?>;
            window.VARIETY_CONFIG = <?php echo json_encode([
                'displayGameCount' => $gamerscoreDisplayCount,
            ]); ?>;
        </script>
        <script src="assets/js/app.js" defer></script>
    <?php endif; ?>
</body>
</html>
