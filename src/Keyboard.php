<?php
namespace irwinlopez1023\Magma4telegram;

class Keyboard {
    private array $keyboard = [];
    private string $type;

    public function __construct(string $type = 'inline_keyboard') {
        $this->type = $type;
    }
    public static function inline(): self {
        return new self('inline_keyboard');
    }

    public static function reply(): self {
        return new self('keyboard');
    }
    public function row(): self {
        $this->keyboard[] = [];
        return $this;
    }
    public function button(string $text, string $data): self {
        if (empty($this->keyboard)) {
            $this->row();
        }

        $lastIndex = count($this->keyboard) - 1;

        $button = ['text' => $text];

        if (filter_var($data, FILTER_VALIDATE_URL)) {
            $button['url'] = $data;
        } else {
            $button['callback_data'] = $data;
        }

        $this->keyboard[$lastIndex][] = $button;
        return $this;
    }

    public function get(): array {
        return [$this->type => $this->keyboard];
    }
}