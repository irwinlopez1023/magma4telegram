<?php
if (!isset($argc) || $argc < 2) {
    exit(1);
}

$logFile = __DIR__ . '/../runner_log.txt';
$logEnabled = false;

function log_runner($msg) {
    global $logFile, $logEnabled;
    if ($logEnabled) {
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] " . $msg . PHP_EOL, FILE_APPEND);
    }
}

// Adjust paths to find the Composer autoloader in a Laravel project
$autoloaderPaths = [
    __DIR__ . '/../../../autoload.php', // Correct: 3 levels up to reach project_root/vendor/autoload.php
    __DIR__ . '/../../autoload.php',       // 2 levels up (for local package development)
    __DIR__ . '/../vendor/autoload.php',    // 1 level up (for other environments)
];

$autoloaderLoaded = false;
foreach ($autoloaderPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $autoloaderLoaded = true;
        log_runner("Autoloader loaded from: " . realpath($path));
        break;
    }
}

if (!$autoloaderLoaded) {
    log_runner("Could not find autoload.php for Magma4Telegram Runner. Paths tried: " . json_encode($autoloaderPaths));
    exit(1);
}

use irwinlopez1023\Magma4telegram\Magma;
use irwinlopez1023\Magma4telegram\MagmaJob;

try {
    $data = json_decode(base64_decode($argv[1]), true);

    if (!is_array($data) || !isset($data['token'], $data['jobClass'], $data['payload'])) {
        throw new \Exception("Invalid data passed to Runner.");
    }

    $token = $data['token'];
    $jobClass = $data['jobClass'];
    $payload = $data['payload'];
    $bootstrapPath = $data['bootstrapPath'] ?? null;
    $logEnabled = $data['runnerLogging'] ?? false;

    log_runner("Job: {$jobClass}, BootstrapPath: {$bootstrapPath}");

    if ($bootstrapPath && file_exists($bootstrapPath)) {
        $_SERVER['SCRIPT_FILENAME'] = $bootstrapPath;
        require_once $bootstrapPath;
        log_runner("Bootstrap {$bootstrapPath} loaded successfully.");
    }

    $magma = new Magma($token);

    if (!class_exists($jobClass)) {
        throw new \Exception("Job class '$jobClass' does not exist. Make sure the autoloader can find it or send a bootstrapPath.");
    }

    if (!is_subclass_of($jobClass, MagmaJob::class)) {
        throw new \Exception("Job class '$jobClass' must inherit from MagmaJob.");
    }

    log_runner("Executing handle() in {$jobClass}");
    $job = new $jobClass($magma);
    $job->handle($payload);
    log_runner("Job finished successfully.");

} catch (\Throwable $e) {
    log_runner("Error in Job Runner: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
}
