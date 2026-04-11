<?php
namespace irwinlopez1023\Magma4telegram;
use Exception;

class MagmaCommand{
    public array $params = [];
    protected ?string $botToken = null;
    protected ?string $bootstrapPath = null;

    protected bool $enabled = true;
    protected static array $aliases = [];

    public function setArguments($argument): void
    {
        $this->params = $argument;
    }

    public function setBotToken(string $token): void
    {
        $this->botToken = $token;
    }
    public function setBootstrapPath(string $path): void
    {
        $this->bootstrapPath = $path;
    }

    /**
     * @throws Exception
     */
    public function argument($argument){
        if (!isset($this->params[$argument])) throw new Exception('This argument does not exist');
        return $this->params[$argument];
    }

    protected function dispatchAsync(string $jobClass, array $payload = []): void
    {
        $bootstrapPath = $this->bootstrapPath ?? $_SERVER['SCRIPT_FILENAME'] ?? null;
        
        $reflection = new \ReflectionClass($jobClass);
        $runnerLogging = $reflection->getDefaultProperties()['magmaRunnerLogging'] ?? false;

        $data = [
            'token' => $this->botToken,
            'jobClass' => $jobClass,
            'payload' => $payload,
            'bootstrapPath' => $bootstrapPath,
            'runnerLogging' => $runnerLogging
        ];
        
        $dataBase64 = base64_encode(json_encode($data));
        $runnerPath = realpath(__DIR__ . '/Runner.php');

        if ($runnerPath === false) {
            throw new Exception("Runner.php not found");
        }

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $cmd = "php \"{$runnerPath}\" {$dataBase64}";
            if (class_exists('COM')) {
                try {
                    $WshShell = new \COM("WScript.Shell");
                    $WshShell->Run("cmd /c " . $cmd, 0, false);
                } catch (Exception $e) {
                    pclose(popen("start /B " . $cmd, "r"));
                }
            } else {
                pclose(popen("start /B " . $cmd, "r"));
            }
        } else {
            // Linux/Mac
            exec("php \"{$runnerPath}\" {$dataBase64} > /dev/null 2>&1 &");
        }
    }
}
