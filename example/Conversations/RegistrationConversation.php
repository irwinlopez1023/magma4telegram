<?php
namespace Conversations;

use irwinlopez1023\Magma4telegram\MagmaConversation;
use irwinlopez1023\Magma4telegram\Keyboard;

class RegistrationConversation extends MagmaConversation
{
    /**
     * Paso 1: Pedir el nombre.
     */
    public function start(): void
    {
        $this->ask("¡Hola! Vamos a registrarte. ¿Cómo te llamas?");
        $this->next('askAge');
    }

    /**
     * Paso 2: Guardar nombre y pedir edad.
     */
    public function askAge(string $response): void
    {
        $this->saveData('name', $response);
        $this->ask("Mucho gusto, {$response}. ¿Cuántos años tienes?");
        $this->next('askColor');
    }

    /**
     * Paso 3: Guardar edad y mostrar menú de colores + botón de comando.
     */
    public function askColor(string $response): void
    {
        $this->saveData('age', $response);

        $keyboard = Keyboard::inline()
            ->row()
            ->button('🔴 Rojo', 'Rojo')
            ->button('🔵 Azul', 'Azul')
            ->row()
            ->button('🟢 Verde', 'Verde')
            ->row()
            ->button('🆔 Consultar mi ID ahora', '/id') // Comando directo
            ->get();

        $this->ask("Perfecto. Ahora elige tu color favorito:", $keyboard);
        $this->next('finish');
    }

    /**
     * Paso 4: Mostrar resumen y finalizar.
     */
    public function finish(string $response): void
    {
        // Si el usuario presionó /id, el bot responderá con el ID 
        // pero la conversación seguirá esperando aquí. 
        // Podríamos validar si la respuesta es un color válido.
        
        $name = $this->getData('name');
        $age = $this->getData('age');
        $color = $response;

        if ($color === '/id') {
            $this->ask("Ya viste tu ID arriba. Pero dime, ¿qué color prefieres? (Rojo, Azul o Verde)");
            $this->next('finish');
            return;
        }

        $summary = "✅ <b>Registro Completado</b>\n\n" .
                   "👤 Nombre: {$name}\n" .
                   "🎂 Edad: {$age}\n" .
                   "🎨 Color: {$color}";

        $this->ask($summary);
        $this->end();
    }
}
