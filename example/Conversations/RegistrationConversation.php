<?php

namespace Conversations;

use irwinlopez1023\Magma4telegram\MagmaConversation;

class RegistrationConversation extends MagmaConversation
{
    public function start(): void
    {
        $this->ask("¡Hola! Vamos a registrarte. ¿Cuál es tu nombre?");
        // El siguiente mensaje que envíe el usuario será manejado por el método "askAge"
        $this->next('askAge');
    }

    public function askAge(string $response): void
    {
        // Guardamos el nombre que acaba de darnos
        $this->saveData('name', $response);

        $this->ask("Mucho gusto, {$response}. ¿Cuántos años tienes?");
        // El siguiente mensaje será manejado por "finish"
        $this->next('finish');
    }

    public function finish(string $response): void
    {
        $name = $this->getData('name');
        $age = $response;

        $this->ask("¡Perfecto! Te has registrado con éxito.\nNombre: {$name}\nEdad: {$age}\n\n¡Gracias por usar Magma4Telegram!");

        // Terminamos la conversación y limpiamos el estado
        $this->end();
    }
}
