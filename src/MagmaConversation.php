<?php

namespace irwinlopez1023\Magma4telegram;

abstract class MagmaConversation
{
    use MagmaSend;

    protected Magma $magma;
    protected ConversationManager $manager;
    protected string $chatId;
    protected array $data = [];

    public function __construct(Magma $magma, string $chatId)
    {
        $this->magma = $magma;
        $this->MagmaSetBotToken($magma->getBotToken());
        $this->manager = ConversationManager::getInstance(Magma::getStoragePath());
        $this->chatId = $chatId;
        
        $state = $this->manager->getState($this->chatId);
        if ($state && isset($state['data'])) {
            $this->data = $state['data'];
        }
    }

    public function getLastMessageId(): ?string
    {
        return $this->data['_current_msg_id'] ?? null;
    }

    public function reset(): void
    {
        $this->data = [];
        $this->manager->clearState($this->chatId);
    }

    public function ask(string $text, $replyMarkup = null): void
    {
        $this->sendTelegramMessage($this->chatId, $text, 'html', $replyMarkup);
    }

    public function next(string $methodName): void
    {
        $state = [
            'class' => static::class,
            'next_step' => $methodName,
            'data' => $this->data,
        ];
        
        $this->manager->saveState($this->chatId, $state);
    }

    public function saveData(string $key, $value): void
    {
        $this->data[$key] = $value;
        $state = $this->manager->getState($this->chatId);
        
        if ($state === null) {
            $state = [
                'class' => static::class,
                'next_step' => null,
                'data' => []
            ];
        }

        $state['data'] = $this->data;
        $this->manager->saveState($this->chatId, $state);
    }

    public function getData(string $key)
    {
        return $this->data[$key] ?? null;
    }

    public function end(): void
    {
        $this->manager->clearState($this->chatId);
    }
}
