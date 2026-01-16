<?php
/**
 * GitHub Webhook Deploy Script
 *
 * Ce script reçoit les webhooks GitHub et déclenche le déploiement.
 * URL: https://hub.wabeo.work/deploy.php
 */

// Configuration
$appPath = dirname(__DIR__); // Racine du projet (parent de public/)
$secret = getenv('DEPLOY_WEBHOOK_SECRET') ?: @file_get_contents($appPath . '/.webhook_secret');
$secret = trim($secret);
$logFile = $appPath . '/storage/logs/deploy.log';

// Fonction de log
function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $dir = dirname($logFile);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

// Vérifier la méthode
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method not allowed');
}

// Récupérer le payload
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';

// Vérifier la signature
if (empty($secret)) {
    logMessage('ERROR: Webhook secret not configured');
    http_response_code(500);
    die('Server configuration error');
}

$expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $secret);

if (!hash_equals($expectedSignature, $signature)) {
    logMessage('ERROR: Invalid signature');
    http_response_code(403);
    die('Invalid signature');
}

// Décoder le payload
$data = json_decode($payload, true);

// Vérifier que c'est un push sur main
$ref = $data['ref'] ?? '';
if ($ref !== 'refs/heads/main') {
    logMessage("INFO: Ignored push to $ref (not main)");
    echo "Ignored: not main branch";
    exit(0);
}

logMessage('INFO: Deployment started');

// Exécuter le déploiement
$output = [];
$returnCode = 0;

// Changer de répertoire
chdir($appPath);

// Git pull
exec('git fetch origin 2>&1', $output, $returnCode);
exec('git reset --hard origin/main 2>&1', $output, $returnCode);

if ($returnCode !== 0) {
    logMessage('ERROR: Git pull failed - ' . implode("\n", $output));
    http_response_code(500);
    die('Git pull failed');
}

logMessage('INFO: Git pull successful');

// Composer install (si composer.json existe)
if (file_exists($appPath . '/composer.json')) {
    exec('composer install --no-dev --optimize-autoloader --no-interaction 2>&1', $output, $returnCode);
    if ($returnCode !== 0) {
        logMessage('WARNING: Composer install failed - ' . implode("\n", $output));
    } else {
        logMessage('INFO: Composer install successful');
    }
}

// Laravel commands (si artisan existe)
if (file_exists($appPath . '/artisan')) {
    exec('php artisan config:cache 2>&1', $output, $returnCode);
    exec('php artisan route:cache 2>&1', $output, $returnCode);
    exec('php artisan view:cache 2>&1', $output, $returnCode);
    exec('php artisan migrate --force 2>&1', $output, $returnCode);
    logMessage('INFO: Laravel commands executed');
}

logMessage('INFO: Deployment completed successfully');

// Réponse
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'Deployment completed',
    'output' => implode("\n", array_slice($output, -20)) // Dernières 20 lignes
]);
