<?php

namespace irwinlopez1023\Magma4telegram;
use Exception;

class ConversationManager
{
    private string $storagePath;

    /**
     * @throws Exception
     */
    public function __construct(?string $storagePath = null)
    {
        $this->storagePath = $storagePath ?? __DIR__ . '/storage/conversations';

        if (!is_dir($this->storagePath)) {
            if (!mkdir($this->storagePath, 0777, true) && !is_dir($this->storagePath)) {
                throw new Exception("ConversationManager could not create storage directory: {$this->storagePath}");
            }
        }
    }

    private function getFilePath(string $chatId): string
    {
        return $this->storagePath . "/chat_{$chatId}.json";
    }

    public function hasActiveConversation(string $chatId): bool
    {
        return file_exists($this->getFilePath($chatId));
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
        $written = fwrite($fp, json_encode($state, JSON_PRETTY_PRINT));
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
}
