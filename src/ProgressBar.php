<?php
namespace irwinlopez1023\Magma4telegram;

class ProgressBar
{
    private $magma;
    private string $chatId;
    private string $messageId;
    private int $size;

    public function __construct($magma, string $chatId, string $messageId, int $size = 10)
    {
        $this->magma = $magma;
        $this->chatId = $chatId;
        $this->messageId = $messageId;
        $this->size = $size;
    }

    public static function fromIds($magma, string $chatId, string $messageId, int $size = 10): self
    {
        return new self($magma, $chatId, $messageId, $size);
    }

    public function getChatId(): string
    {
        return $this->chatId;
    }

    public function getMessageId(): string
    {
        return $this->messageId;
    }

    public function update(int $percent, string $text = ""): void
    {
        $percent = max(0, min(100, $percent));
        $filled = (int) round(($percent / 100) * $this->size);
        $empty = $this->size - $filled;
        $bar = str_repeat('█', $filled) . str_repeat('░', $empty);
        $message = "{$text}\n\n<code>[{$bar}] {$percent}%</code>";
        $this->magma->editTelegramMessage($this->chatId, $this->messageId, $message, 'html');
    }
}
