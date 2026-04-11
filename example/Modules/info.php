<?php
namespace Modules;

use irwinlopez1023\Magma4telegram\MagmaCommand;
use irwinlopez1023\Magma4telegram\MagmaSend;
use Exception;

class info extends MagmaCommand {
    use MagmaSend;

    protected string $command = "/info {name}";
    protected static array $aliases = ['info', 'help'];
    protected ?string $chatId = null;

    public function handle(): void
    {
        try {
            $this->sendTelegramMessage($this->chatId, "Hello " . $this->argument('name'));
        } catch (Exception $e) {
            echo $e->getMessage() . "\n";
        }
    }
}
