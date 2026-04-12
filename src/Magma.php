<?php
namespace irwinlopez1023\Magma4telegram;

use FilesystemIterator;
use ReflectionException;
use Exception;
use ReflectionClass;
use Throwable;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

class Magma {
    use MagmaSend;
    private static array $reflectionCache = [];
    private ?string $botToken;
    public static array $magma = [];
    private static bool $isHandling = false;
    private static ?string $storagePath = null;

    /**
     * @throws Exception
     * @throws Throwable
     */
    public function __construct(string $botToken) {
        $this->botToken = $botToken;
        if (php_sapi_name() === 'cli') {
            return;
        }

        if (self::$isHandling) {
            return;
        }
        
        self::$isHandling = true;
        try {
            $this->handleRequest();
        } finally {
            self::$isHandling = false;
        }
    }

    public static function setStoragePath(string $path): void {
        self::$storagePath = $path;
    }

    public static function getStoragePath(): ?string {
        return self::$storagePath;
    }

    /**
     * @throws Exception
     * @throws Throwable
     */
    private function handleRequest(): void {
        $this->MagmaSetBotToken($this->botToken);
        $request = new TelegramRequest();
        $chatId = (string) $request->getChatId();
        $messageId = $request->getMessageId();

        $isCallback = $request->isCallbackQuery();
        $inputText = null;
        
        try {
            $inputText = $isCallback ? $request->getCallbackData() : $request->getCommand();
        } catch (Exception $e) {
            // No hay texto ni data, ignoramos la petición (ej: una foto sin caption)
            return;
        }

        if (empty($inputText)) {
            return;
        }

        // --- 1. CONVERSATION INJECTION ---
        // Prioridad máxima: si hay una charla activa, capturamos el input.
        // EXCEPTIONS:
        // - Callback queries with commands (starting with /) interrupt conversation temporarily
        // - Text messages starting with / are treated as commands, not conversation input
        $isCommand = str_starts_with($inputText, '/');
        $isCallbackCommand = $isCallback && $isCommand;
        
        // If it's a command, destroy any active menu first
        if ($isCommand || $isCallbackCommand) {
            $conversationManager = ConversationManager::getInstance(self::$storagePath);
            if ($conversationManager->hasActiveConversation($chatId)) {
                $conversationManager->clearState($chatId);
            }
        }
        
        if (!$isCallbackCommand && !$isCommand) {
            $conversationManager = ConversationManager::getInstance(self::$storagePath);
            if ($conversationManager->hasActiveConversation($chatId)) {
                $state = $conversationManager->getState($chatId);
                if ($state && isset($state['class'], $state['next_step'])) {
                    $conversationClass = $state['class'];
                    $methodName = $state['next_step'];

                    if (class_exists($conversationClass)) {
                        $conversationInstance = new $conversationClass($this, $chatId);
                        if (method_exists($conversationInstance, $methodName)) {
                            $conversationInstance->$methodName($inputText);
                            return; // Detenemos flujo: el input era para la conversación.
                        }
                    }
                }
            }
        }

        // --- 2. CALLBACKS MAPPINGS ---
        // Si es un botón y tiene un método asignado (excepto si empieza por / que es comando)
        if ($isCallback) {
            foreach (self::$magma as $class) {
                try {
                    $reflection = $this->getCachedReflection($class);
                    $defaultProps = $reflection->getDefaultProperties();

                    if (isset($defaultProps['callbacks'][$inputText])) {
                        $methodToCall = $defaultProps['callbacks'][$inputText];
                        $app = $reflection->newInstanceWithoutConstructor();
                        $this->injectContext($app, $reflection, $chatId, $messageId);
                        
                        if (method_exists($app, $methodToCall)) {
                            $app->$methodToCall();
                            return;
                        }
                    }
                } catch (Exception $e) {
                    error_log("Magma Callback Error: " . $e->getMessage());
                }
            }
            
            // Si el callback no tiene mapeo y no es un comando (/), lo ignoramos.
            if (!str_starts_with($inputText, '/')) {
                return;
            }
        }

        // --- 3. COMMAND ROUTING ---
        $command = trim($inputText);
        $commandName = preg_split('/\s+/', $command)[0];

        // Built-ins
        if ($commandName === '/ping' || $commandName === 'ping') {
            $this->sendTelegramMessage($chatId, 'Pong!');
            return;
        }
        if ($commandName === '/powered' || $commandName === 'powered') {
            $this->sendTelegramMessage($chatId, 'Magma4Telegram!');
            return;
        }

        foreach (self::$magma as $class) {
            try {
                $reflection = $this->getCachedReflection($class);
                if (!$reflection->hasProperty('command')) continue;

                $classCommand = $reflection->getProperty('command')->getDefaultValue();
                $userCommand = $this->parseCommand($classCommand);
                $commandMatched = ($commandName === $userCommand['commandName']);

                if (!$commandMatched && $reflection->hasProperty('aliases')) {
                    $aliases = $reflection->getProperty('aliases')->getDefaultValue();
                    if (in_array($commandName, $aliases, true)) $commandMatched = true;
                }

                if ($commandMatched) {
                    $expectedArgsCount = count($userCommand['args']);
                    $limit = $expectedArgsCount > 0 ? $expectedArgsCount + 1 : 1;
                    $inputParts = preg_split('/\s+/', $command, $limit);
                    array_shift($inputParts);
                    $arguments = $inputParts;

                    try {
                        $this->validateArguments($arguments, $userCommand['args']);
                    } catch (Exception $e) {
                        $this->sendTelegramMessage($chatId, "❌ Invalid usage.\n\nUsage: " . $this->getUsageString($userCommand));
                        return;
                    }

                    $app = $reflection->newInstanceWithoutConstructor();
                    $mappedArguments = array_combine($userCommand['args'], $arguments) ?: [];
                    if (method_exists($app, 'setArguments')) $app->setArguments($mappedArguments);
                    $this->injectContext($app, $reflection, $chatId, $messageId);

                    if (method_exists($app, 'handle')) {
                        $app->handle();
                    }
                    return;
                }
            } catch (Exception $e) {
                error_log("Magma Routing Error: " . $e->getMessage());
            }
        }
    }

    private function injectContext($app, ReflectionClass $reflection, $chatId, $messageId): void {
        if ($reflection->hasProperty('chatId')) {
            $prop = $reflection->getProperty('chatId');
            $prop->setAccessible(true);
            $prop->setValue($app, $chatId);
        }
        if ($reflection->hasProperty('incomingMessageId')) {
            $prop = $reflection->getProperty('incomingMessageId');
            $prop->setAccessible(true);
            $prop->setValue($app, $messageId);
        }
        if (method_exists($app, 'MagmaSetBotToken')) {
            $app->MagmaSetBotToken($this->botToken);
        }
        if (method_exists($app, 'setBotToken')) {
            $app->setBotToken($this->botToken);
        }
    }

    private function getUsageString(array $userCommand): string {
        $usage = $userCommand['commandName'];
        if (!empty($userCommand['args'])) {
            $usage .= ' ' . implode(' ', array_map(fn($a) => "`{$a}`", $userCommand['args']));
        }
        return $usage;
    }

    public static function autoDiscoverCommands(string $absolutePath, string $baseNamespace): void {
        if (!is_dir($absolutePath)) return;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($absolutePath, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $relativePath = str_replace($absolutePath, '', $file->getPathname());
                $namespacePath = str_replace(['/', '\\'], '\\', ltrim($relativePath, '/\\'));
                $className = rtrim($baseNamespace, '\\') . '\\' . preg_replace('/\.php$/', '', $namespacePath);
                if (!class_exists($className, false)) require_once $file->getPathname();
                if (class_exists($className) && is_subclass_of($className, MagmaCommand::class)) {
                    $reflection = new ReflectionClass($className);
                    $props = $reflection->getDefaultProperties();
                    if (isset($props['enabled']) && $props['enabled'] === false) continue;
                    if (!in_array($className, self::$magma, true)) self::$magma[] = $className;
                }
            }
        }
    }

    public static function autoDiscoverConversations(string $absolutePath, string $baseNamespace): void {
        if (!is_dir($absolutePath)) return;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($absolutePath, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $relativePath = str_replace($absolutePath, '', $file->getPathname());
                $namespacePath = str_replace(['/', '\\'], '\\', ltrim($relativePath, '/\\'));
                $className = rtrim($baseNamespace, '\\') . '\\' . preg_replace('/\.php$/', '', $namespacePath);
                if (!class_exists($className, false)) require_once $file->getPathname();
            }
        }
    }

    public static function autoDiscoverJobs(string $absolutePath, string $baseNamespace): void {
        if (!is_dir($absolutePath)) return;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($absolutePath, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $relativePath = str_replace($absolutePath, '', $file->getPathname());
                $namespacePath = str_replace(['/', '\\'], '\\', ltrim($relativePath, '/\\'));
                $className = rtrim($baseNamespace, '\\') . '\\' . preg_replace('/\.php$/', '', $namespacePath);
                if (!class_exists($className, false)) require_once $file->getPathname();
            }
        }
    }

    public function getBotToken(): ?string {
        return $this->botToken;
    }

    private function getCachedReflection(string $class): ReflectionClass {
        if (!isset(self::$reflectionCache[$class])) self::$reflectionCache[$class] = new ReflectionClass($class);
        return self::$reflectionCache[$class];
    }

    private function validateArguments(array $providedArgs, array $expectedArgs): void {
        if (count($providedArgs) !== count($expectedArgs)) throw new Exception('Invalid number of parameters');
    }

    private function parseCommand(string $commandTemplate): array {
        $parts = preg_split('/\s+/', trim($commandTemplate));
        $commandName = array_shift($parts);
        $args = array_map(fn($arg) => trim($arg, '{}'), $parts);
        return compact('commandName', 'args');
    }
}
