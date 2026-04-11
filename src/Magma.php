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
        $chatId = $request->getChatId();
        $messageId = $request->getMessageId();


        if (method_exists($request, 'isCallbackQuery') && $request->isCallbackQuery()) {
            $callbackData = $request->getCallbackData();

            foreach (self::$magma as $class) {
                try {
                    $reflection = $this->getCachedReflection($class);
                    $defaultProps = $reflection->getDefaultProperties();

                    if (isset($defaultProps['callbacks'][$callbackData])) {
                        $methodToCall = $defaultProps['callbacks'][$callbackData];
                        $app = $reflection->newInstanceWithoutConstructor();
                        $chatIdProp = $reflection->getProperty('chatId');
                        $chatIdProp->setAccessible(true);
                        $chatIdProp->setValue($app, $chatId);
                        if ($reflection->hasProperty('incomingMessageId')) {
                            $msgIdProp = $reflection->getProperty('incomingMessageId');
                            $msgIdProp->setAccessible(true);
                            $msgIdProp->setValue($app, $messageId);
                        }
                        if (method_exists($app, 'MagmaSetBotToken')) {
                            $app->MagmaSetBotToken($this->botToken);
                        }
                        if (method_exists($app, 'setBotToken')) {
                            $app->setBotToken($this->botToken);
                        }
                        if (method_exists($app, $methodToCall)) {
                            try {
                                $app->$methodToCall();
                            } catch (Throwable $e) {
                                error_log("Magma Callback Error: " . $e->getMessage());
                            }
                            return;
                        } else {
                            error_log("Magma Callback Error: Registered method '$methodToCall' does not exist on " . $class);
                            return;
                        }
                    }
                } catch (ReflectionException $e) {
                    throw new Exception($e->getMessage(), 0, $e);
                }
            }
            return;
        }

        // --- CONVERSATION INJECTION ---
        try {
            $conversationManager = new ConversationManager(self::$storagePath);
            if ($conversationManager->hasActiveConversation($chatId)) {
                $state = $conversationManager->getState($chatId);
                if ($state && isset($state['class']) && isset($state['next_step'])) {
                    $conversationClass = $state['class'];
                    $methodName = $state['next_step'];

                    if (class_exists($conversationClass)) {
                        $conversationInstance = new $conversationClass($this, $chatId);
                        if (method_exists($conversationInstance, $methodName)) {
                            // Pass the message text as argument
                            try {
                                $commandStr = $request->getCommand();
                            } catch (Exception $e) {
                                return; // User sent a photo without caption or other unsupported message type, exit conversation without silencing real exceptions
                            }

                            // The conversation logic call happens OUTSIDE the general try block
                            // so if the developer throws an Exception or a legitimate failure occurs, it propagates
                            // and can be debugged (or handled at the Framework entrypoint)
                            $conversationInstance->$methodName($commandStr);
                            return; // Blocks normal flow
                        } else {
                            error_log("Magma Conversation Error: Registered method '$methodName' does not exist on " . $conversationClass);
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            // Allow exceptions from ConversationManager or conversation logic to propagate naturally
            throw $e;
        }
        // --- END CONVERSATION INJECTION ---

        try {
            $commandStr = $request->getCommand();
        } catch (Exception $e) {
            return; // If command cannot be parsed (e.g. is a photo without caption), simply exit
        }

        $command = trim($commandStr);
        if (empty($command)) {
            return;
        }

        $commandName = preg_split('/\s+/', $command)[0];

        // Default /ping command - always available as health check
        if ($commandName === '/ping' || $commandName === 'ping') {
            $this->sendTelegramMessage($chatId, 'Pong!');
            return;
        }

        // Default /powered command - branding
        if ($commandName === '/powered' || $commandName === 'powered') {
            $this->sendTelegramMessage($chatId, 'Magma4Telegram!');
            return;
        }

        foreach (self::$magma as $class) {
            try {
                $reflection = $this->getCachedReflection($class);
                
                if (!$reflection->hasProperty('command')) {
                    continue; // Skip silently if no command property exists
                }
                
                $classCommand = $reflection->getProperty('command')->getDefaultValue();
                $userCommand = $this->parseCommand($classCommand);
                $commandMatched = $commandName === $userCommand['commandName'];
                
                if (!$commandMatched && $reflection->hasProperty('aliases')) {
                    $aliases = $reflection->getProperty('aliases')->getDefaultValue();
                    if (in_array($commandName, $aliases, true)) {
                        $commandMatched = true;
                    }
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
                        $usage = $userCommand['commandName'];
                        if (!empty($userCommand['args'])) {
                            $usage .= ' ' . implode(' ', array_map(fn($a) => "`{$a}`", $userCommand['args']));
                        }
                        try {
                            $this->sendTelegramMessage($chatId, "❌ Invalid usage.\n\nUsage: {$usage}");
                        } catch (Exception $sendError) {
                            error_log("Magma sendTelegramMessage Error: " . $sendError->getMessage());
                        }
                        return;
                    }
                    
                    $mappedArguments = [];
                    foreach ($userCommand['args'] as $index => $argName) {
                        $mappedArguments[$argName] = $arguments[$index];
                    }
                    $app = $reflection->newInstanceWithoutConstructor();
                    
                    if (method_exists($app, 'setArguments')) {
                        $app->setArguments($mappedArguments);
                    }
                    
                    if ($reflection->hasProperty('chatId')) {
                        $chatIdProp = $reflection->getProperty('chatId');
                        $chatIdProp->setAccessible(true);
                        $chatIdProp->setValue($app, $chatId);
                    }
                    
                    if (method_exists($app, 'MagmaSetBotToken')) {
                        $app->MagmaSetBotToken($this->botToken);
                    }
                    if (method_exists($app, 'setBotToken')) {
                        $app->setBotToken($this->botToken);
                    }
                    
                    if (method_exists($app, 'handle')) {
                        $app->handle();
                    } else {
                        error_log("Magma Command Error: Method 'handle' does not exist on " . $class);
                    }
                    return;
                }
            } catch (ReflectionException $e) {
                throw new Exception($e->getMessage(), 0, $e);
            }
        }
        throw new Exception("Command not found");
    }

    /**
     * @throws Exception
     */
    public static function autoDiscoverCommands(string $absolutePath, string $baseNamespace): void
    {
        if (!is_dir($absolutePath)) {
            throw new Exception("The directory does not exist: $absolutePath");
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($absolutePath, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $relativePath = str_replace($absolutePath, '', $file->getPathname());
                $namespacePath = str_replace(['/', '\\'], '\\', ltrim($relativePath, '/\\'));
                $namespacePathWithoutExt = preg_replace('/\.php$/', '', $namespacePath);
                $className = rtrim($baseNamespace, '\\') . '\\' . ltrim($namespacePathWithoutExt, '\\');
                
                // Require_once if the class has not been loaded yet
                if (!class_exists($className, false)) {
                    require_once $file->getPathname();
                }

                if (class_exists($className)) {
                    if (is_subclass_of($className, MagmaCommand::class)) {
                        try {
                            $reflection = new ReflectionClass($className);
                            $props = $reflection->getDefaultProperties();
                            if (array_key_exists('enabled', $props) && $props['enabled'] === false) {
                                continue;
                            }
                            if (!in_array($className, self::$magma, true)) {
                                self::$magma[] = $className;
                            }
                        } catch (ReflectionException $e) {
                            continue;
                        }
                    }
                }
            }
        }
    }

    public static function autoDiscoverConversations(string $absolutePath, string $baseNamespace): void
    {
        if (!is_dir($absolutePath)) return;
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($absolutePath, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $relativePath = str_replace($absolutePath, '', $file->getPathname());
                $namespacePath = str_replace(['/', '\\'], '\\', ltrim($relativePath, '/\\'));
                $namespacePathWithoutExt = preg_replace('/\.php$/', '', $namespacePath);
                $className = rtrim($baseNamespace, '\\') . '\\' . ltrim($namespacePathWithoutExt, '\\');
                
                if (!class_exists($className, false)) {
                    require_once $file->getPathname();
                }
            }
        }
    }

    public static function autoDiscoverJobs(string $absolutePath, string $baseNamespace): void
    {
        if (!is_dir($absolutePath)) return;
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($absolutePath, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $relativePath = str_replace($absolutePath, '', $file->getPathname());
                $namespacePath = str_replace(['/', '\\'], '\\', ltrim($relativePath, '/\\'));
                $namespacePathWithoutExt = preg_replace('/\.php$/', '', $namespacePath);
                $className = rtrim($baseNamespace, '\\') . '\\' . ltrim($namespacePathWithoutExt, '\\');
                
                if (!class_exists($className, false)) {
                    require_once $file->getPathname();
                }
            }
        }
    }

    public function getBotToken(): ?string {
        return $this->botToken;
    }

    /**
     * @throws ReflectionException
     */
    private function getCachedReflection(string $class): ReflectionClass {
        if (!isset(self::$reflectionCache[$class])) self::$reflectionCache[$class] = new ReflectionClass($class);
        return self::$reflectionCache[$class];
    }

    /**
     * @throws Exception
     */
    private function validateArguments(array $providedArgs, array $expectedArgs): void {
        if (count($providedArgs) !== count($expectedArgs)) {
            throw new Exception('Invalid number of parameters');
        }
    }

    private function parseCommand(string $commandTemplate): array {
        $parts = preg_split('/\s+/', trim($commandTemplate));
        $commandName = array_shift($parts);
        $args = array_map(function($arg) {
            return trim($arg, '{}');
        }, $parts);
        return compact('commandName', 'args');
    }
}