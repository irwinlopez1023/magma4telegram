<?php

namespace Modules;

use irwinlopez1023\Magma4telegram\Magma;
use irwinlopez1023\Magma4telegram\MagmaCommand;
use irwinlopez1023\Magma4telegram\MagmaSend;
use Conversations\RegistrationConversation;
use Exception;

class register extends MagmaCommand {
    use MagmaSend;

    protected string $command = "/register";
    protected ?string $chatId = null;

    public function handle(): void
    {
        try {
            // Creamos la instancia de Magma usando el token que el comando ya tiene inyectado
            $magma = new Magma($this->botToken);
            
            // Instanciamos la conversación pasando la instancia de Magma y el chatId
            $conversation = new RegistrationConversation($magma, $this->chatId);
            
            // Iniciamos el flujo de la conversación
            $conversation->start();
            
        } catch (Exception $e) {
            echo $e->getMessage() . "\n";
        }
    }
}
