<?php
namespace irwinlopez1023\Magma4telegram;

use Exception;

class TelegramRequest
{
    private array $package;

    /**
     * @throws Exception
     */
    public function __construct(?string $input = null)
    {
        $content = $input ?? file_get_contents('php://input');
        $package = json_decode($content, true);
        if (!is_array($package)) throw new Exception("Empty Data");
        $this->package = $package;
    }
    public function isCallbackQuery(): bool
    {
        return isset($this->package['callback_query']);
    }

    public function getCallbackData(): ?string
    {
        return $this->package['callback_query']['data'] ?? null;
    }

    /**
     * @throws Exception
     */
    public function getCommand(): string
    {
        if (!isset($this->package['message']['text'])) throw new Exception('Command not found');

        $command = $this->package['message']['text'];

        if (empty($command)) {
            throw new Exception('Command not found');
        }

        return $command;
    }

    /**
     * @throws Exception
     */
    public function getChatId()
    {
        if ($this->isCallbackQuery()) {
            if (!isset($this->package['callback_query']['message']['chat']['id'])) {
                throw new Exception('ChatId not found in callback');
            }
            return (string) $this->package['callback_query']['message']['chat']['id'];
        }
        if (!isset($this->package['message']['chat']['id'])) {
            throw new Exception('ChatId not found');
        }
        $chatId = (string) $this->package['message']['chat']['id'];
        if (empty($chatId)) {
            throw new Exception('ChatId not found');
        }
        return $chatId;
    }

    public function getMessageId(): ?string
    {
        if ($this->isCallbackQuery()) {
            return isset($this->package['callback_query']['message']['message_id']) ? (string) $this->package['callback_query']['message']['message_id'] : null;
        }
        return isset($this->package['message']['message_id']) ? (string) $this->package['message']['message_id'] : null;
    }


    public function getPackage(): array
    {
        return $this->package;
    }
}