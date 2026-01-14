<?php
$playedCount = filter_input(INPUT_GET, 'played', FILTER_VALIDATE_INT, [
    'options' => [
        'default' => null,
        'min_range' => 0,
    ],
]);

$totalCount = filter_input(INPUT_GET, 'total', FILTER_VALIDATE_INT, [
    'options' => [
        'default' => null,
        'min_range' => 0,
    ],
]);

$score = filter_input(INPUT_GET, 'score', FILTER_VALIDATE_INT, [
    'options' => [
        'default' => 0,
        'min_range' => 0,
        'max_range' => 100,
    ],
]);

if (is_int($playedCount) && is_int($totalCount) && $totalCount > 0) {
    $playedCount = min($playedCount, $totalCount);
    $score = (int) round(($playedCount / $totalCount) * 100);
} else {
    $totalCount = is_int($totalCount) && $totalCount > 0 ? $totalCount : 100;
    $playedCount = is_int($playedCount)
        ? max(0, min($playedCount, $totalCount))
        : (int) round(($score / 100) * $totalCount);
    $score = $totalCount > 0 ? (int) round(($playedCount / $totalCount) * 100) : 0;
}

$taglineInput = isset($_GET['tagline']) ? trim((string) $_GET['tagline']) : '';
$taglineInput = preg_replace('/\s+/', ' ', $taglineInput);
$taglineInput = $taglineInput !== null ? $taglineInput : '';

$taglineInput = function_exists('mb_substr')
    ? mb_substr($taglineInput, 0, 80)
    : substr($taglineInput, 0, 80);

$taglinesPath = __DIR__ . '/assets/misc/taglines.json';
$taglinesData = [];

if (is_readable($taglinesPath)) {
    $decoded = json_decode((string) file_get_contents($taglinesPath), true);
    if (is_array($decoded) && isset($decoded['tiers']) && is_array($decoded['tiers'])) {
        $taglinesData = $decoded['tiers'];
    }
}

$taglineForTier = static function (int $scoreValue) use ($taglinesData): string {
    $tiers = [];

    foreach ($taglinesData as $threshold => $options) {
        if (!is_array($options)) {
            continue;
        }
        $tiers[(int) $threshold] = $options;
    }

    $thresholds = array_keys($tiers);
    rsort($thresholds);

    foreach ($thresholds as $threshold) {
        if ($scoreValue >= $threshold) {
            $options = $tiers[$threshold];
            return $options[array_rand($options)];
        }
    }

    return '';
};

$tagline = $taglineInput !== '' ? $taglineInput : $taglineForTier($score);
$imagePath = __DIR__ . '/assets/misc/scorecard.svg';

if (!class_exists('Imagick')) {
    error_log('Scorecard export unavailable: Imagick PHP extension not installed.');
    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Scorecard export is temporarily unavailable because the Imagick PHP extension is missing.';
    exit;
}

if (!file_exists($imagePath)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Scorecard template is missing.';
    exit;
}

$image = new Imagick($imagePath);
$draw = new ImagickDraw();

$draw->setFont('DejaVu-Sans-Bold');
$draw->setFillColor('white');
$draw->setTextAlignment(Imagick::ALIGN_CENTER);
$draw->setFontStyle(Imagick::STYLE_NORMAL);

$width = $image->getImageWidth();
$height = $image->getImageHeight();

$titleSize = 38;
$scoreSize = 46;
$subtitleSize = 22;

$lineSpacing = 1.55;
$blockCenterY = $height * 0.44;

$totalHeight =
    ($titleSize * $lineSpacing) +
    ($scoreSize * $lineSpacing) +
    ($subtitleSize * $lineSpacing);

$y = $blockCenterY - ($totalHeight / 2) + $titleSize;

$draw->setFontSize($titleSize);
$image->annotateImage($draw, $width / 2, $y, 0, 'I scored.');

$y += $titleSize * $lineSpacing;

$draw->setFontSize($scoreSize);
if ($totalCount > 0 && $playedCount === $totalCount) {
    $draw->setFillColor('#FFD700');
    $draw->setStrokeColor('rgba(0,0,0,0.7)');
    $draw->setStrokeWidth(2);
} else {
    $draw->setFillColor('white');
    $draw->setStrokeWidth(0);
}
$scoreLabel = sprintf('%d/%d', $playedCount, $totalCount);
$image->annotateImage($draw, $width / 2, $y, 0, $scoreLabel);

$draw->setFillColor('white');
$draw->setStrokeWidth(0);
$y += $scoreSize * $lineSpacing;

$draw->setFontSize($subtitleSize);
$image->annotateImage($draw, $width / 2, $y, 0, $tagline);

$draw->setFontSize(26);
$draw->setFillColor('rgba(255,255,255,0.75)');

$image->annotateImage(
    $draw,
    $width / 2,
    $height * 0.95,
    0,
    'Variety Game Score by HaywardGG'
);

header('Content-Type: image/png');
$image->setImageFormat('png');
echo $image;
