<?php
namespace Modules;

use irwinlopez1023\Magma4telegram\Magma;
use irwinlopez1023\Magma4telegram\MagmaCommand;
use irwinlopez1023\Magma4telegram\MagmaSend;
use Conversations\RegistrationConversation;
use Exception;

class register extends MagmaCommand {
    use MagmaSend;

    /**
     * El comando que activa el flujo de registro.
     */
    protected string $command = "/register";

    /**
     * Magma inyectará el ID del chat aquí.
     */
    protected ?string $chatId = null;

    public function handle(): void
    {
        try {
            // Re-instanciamos Magma con el token para las dependencias de la conversación
            $magma = new Magma($this->botToken);
            
            // Creamos e iniciamos la conversación
            $conversation = new RegistrationConversation($magma, $this->chatId);
            $conversation->start();
            
        } catch (Exception $e) {
            echo $e->getMessage() . "\n";
        }
    }
}
