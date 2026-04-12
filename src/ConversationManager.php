<?php

namespace irwinlopez1023\Magma4telegram;
use Exception;

class ConversationManager
{
    private static ?self $instance = null;
    private string $storagePath;

    /**
     * @throws Exception
     */
    private function __construct(?string $storagePath = null)
    {
        $this->storagePath = $storagePath ?? __DIR__ . '/storage/conversations';

        if (!is_dir($this->storagePath)) {
            if (!mkdir($this->storagePath, 0777, true) && !is_dir($this->storagePath)) {
                throw new Exception("ConversationManager could not create storage directory: {$this->storagePath}");
            }
        }
    }

    /**
     * @throws Exception
     */
    public static function getInstance(?string $storagePath = null): self
    {
        if (self::$instance === null) {
            self::$instance = new self($storagePath);
        }
        return self::$instance;
    }

    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    public function getStoragePath(): string
    {
        return $this->storagePath;
    }

    public function getFilePath(string $chatId): string
    {
        return $this->storagePath . "/chat_{$chatId}.json";
    }

    public function hasActiveConversation(string $chatId): bool
    {
        $file = $this->getFilePath($chatId);
        return file_exists($file);
    }

    public function getState(string $chatId): ?array
    {
        $file = $this->getFilePath($chatId);
        if (!file_exists($file)) {
            return null;
        }

        $fp = fopen($file, 'r');
        if (!$fp || !flock($fp, LOCK_SH)) {
            return null;
        }

        $content = file_get_contents($file);
        flock($fp, LOCK_UN);
        fclose($fp);

        if ($content === false) {
            return null;
        }
        
        $decoded = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        
        return $decoded;
    }

    /**
     * @throws Exception
     */
    public function saveState(string $chatId, array $state): void
    {
        $file = $this->getFilePath($chatId);
        $fp = fopen($file, 'c');
        if (!$fp) {
            throw new Exception("Cannot open file for writing: {$file}");
        }
        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            throw new Exception("Cannot acquire lock for file: {$file}");
        }
        ftruncate($fp, 0);
        rewind($fp);
        $jsonContent = json_encode($state, JSON_PRETTY_PRINT);
        if ($jsonContent === false) {
            flock($fp, LOCK_UN);
            fclose($fp);
            throw new Exception("Cannot encode state to JSON");
        }
        $written = fwrite($fp, $jsonContent);
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        if ($written === false) {
            throw new Exception("Cannot write to file: {$file}");
        }
    }

    public function clearState(string $chatId): void
    {
        $file = $this->getFilePath($chatId);
        if (file_exists($file)) {
            unlink($file);
        }
    }

    public function clearAllStates(): void
    {
        $files = glob($this->storagePath . '/chat_*.json');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}
