<?php
namespace Modules;

use irwinlopez1023\Magma4telegram\MagmaCommand;
use irwinlopez1023\Magma4telegram\MagmaSend;
use irwinlopez1023\Magma4telegram\Keyboard;
use Exception;

class menu_id extends MagmaCommand {
    use MagmaSend;

    /**
     * Comando inicial para mostrar el menú.
     */
    protected string $command = "/menu";

    /**
     * El ID del chat es inyectado automáticamente.
     */
    protected ?string $chatId = null;

    public function handle(): void
    {
        try {
            // El botón envía '/id' como callback_data. 
            // Magma enrutará esto directamente al comando /id.
            $keyboard = Keyboard::inline()
                ->row()
                ->button('🆔 Obtener mi ID', '/id')
                ->get();

            $this->sendTelegramMessage(
                $this->chatId, 
                "Presiona el botón para ejecutar el comando de identidad:", 
                'html', 
                $keyboard
            );
        } catch (Exception $e) {
            echo $e->getMessage() . "\n";
        }
    }
}
