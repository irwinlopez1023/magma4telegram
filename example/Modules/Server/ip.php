<?php
namespace Modules\Server;

use irwinlopez1023\Magma4telegram\MagmaCommand;
use irwinlopez1023\Magma4telegram\MagmaSend;
use Exception;

class ip extends MagmaCommand {
    use MagmaSend;

    protected string $command = "/ip";
    protected ?string $chatId = null;

    public function handle(): void
    {
        try {
            $ip = $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';
            $hostname = gethostname() ?: 'unknown';
            
            $this->sendTelegramMessage($this->chatId, "🌐 <b>Server Info</b>\n\n" .
                "IP: <code>{$ip}</code>\n" .
                "Hostname: <code>{$hostname}</code>");
        } catch (Exception $e) {
            echo $e->getMessage() . "\n";
        }
    }
}