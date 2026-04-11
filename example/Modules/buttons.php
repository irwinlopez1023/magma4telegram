<?php
namespace Modules;

use irwinlopez1023\Magma4telegram\Keyboard;
use irwinlopez1023\Magma4telegram\MagmaCommand;
use irwinlopez1023\Magma4telegram\MagmaSend;
use irwinlopez1023\Magma4telegram\Interactable;
use Exception;

class buttons extends MagmaCommand {
    use MagmaSend, Interactable;
    protected string $command = "/button {name}";
    protected ?string $chatId = null;
    protected array $callbacks = [
        'ok'     => 'confirm',
        'cancel' => 'cancel'
    ];

    /**
     * @throws Exception
     */
    public function handle(): void
    {
        $botones = Keyboard::inline()
            ->row()->button('Confirm', 'ok')
            ->row()->button('Cancel', 'cancel')
            ->get();

        $this->sendTelegramMessage($this->chatId, "Choose an option:", "html", $botones);
    }
    public function confirm(): void
    {
        $this->answerCallback("You have successfully confirmed the action!");
    }
    public function cancel(): void
    {
        $this->answerCallback("Operation cancelled. Everything is fine.");
    }
}
