<?php
namespace irwinlopez1023\Magma4telegram;

trait Interactable {
    protected ?string $incomingMessageId = null;
    protected function answerCallback(string $text, $buttons = null, string $parseMode = 'html'): void
    {
        if ($this->chatId && $this->incomingMessageId) {
            $this->editTelegramMessage(
                $this->chatId,
                $this->incomingMessageId,
                $text,
                $parseMode,
                $buttons
            );
        }
    }
}