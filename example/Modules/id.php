<?php
namespace Modules;

use irwinlopez1023\Magma4telegram\MagmaCommand;
use irwinlopez1023\Magma4telegram\MagmaSend;
use Exception;

class id extends MagmaCommand {
    use MagmaSend;

    /**
     * El comando que activará esta clase.
     */
    protected string $command = "/id";

    /**
     * El ID del chat/usuario es inyectado automáticamente por Magma.
     */
    protected ?string $chatId = null;

    public function handle(): void
    {
        try {
            $this->sendTelegramMessage($this->chatId, "Tu ID de usuario es: <code>{$this->chatId}</code>");
        } catch (Exception $e) {
            echo $e->getMessage() . "\n";
        }
    }
}
