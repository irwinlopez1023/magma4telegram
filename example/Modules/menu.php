<?php
namespace Modules;

use irwinlopez1023\Magma4telegram\MagmaCommand;
use irwinlopez1023\Magma4telegram\MagmaSend;
use Conversations\MenuDemoConversation;
use Exception;

class menu extends MagmaCommand {
    use MagmaSend;

    protected string $command = "/menu";
    protected ?string $chatId = null;

    public function handle(): void
    {
        try {
            $chatId = (string) $this->chatId;
            $magma = new \irwinlopez1023\Magma4telegram\Magma($this->botToken);
            
            // Iniciamos la conversación. Magma ya se encargó de limpiar el estado
            // y borrar el mensaje anterior en el núcleo (src/Magma.php).
            $conversation = new MenuDemoConversation($magma, $chatId);
            $conversation->start();
            
        } catch (Exception $e) {
            error_log("Menu Command Error: " . $e->getMessage());
        }
    }
}
