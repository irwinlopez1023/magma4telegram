<?php
namespace Conversations;

use irwinlopez1023\Magma4telegram\MagmaMenu;

class MenuDemoConversation extends MagmaMenu
{
    public function start(): void
    {
        $this->showMenu('main', null, true);
    }

    protected function menu_main(): array
    {
        return [
            ['text' => '🔴 Rojo', 'callback' => 'rojo'],
            ['text' => '🔵 Azul', 'callback' => 'azul'],
            ['text' => '🟢 Verde', 'callback' => 'verde'],
            ['text' => '🎨 Ver más colores', 'callback' => 'more_colors'],
        ];
    }

    protected function menu_more_colors(): array
    {
        return [
            ['text' => '⚫ Negro', 'callback' => 'negro'],
            ['text' => '⚪ Blanco', 'callback' => 'blanco'],
            ['text' => '🟠 Naranja', 'callback' => 'naranja'],
            ['text' => '🔙 Regresar', 'callback' => '<<back'],
        ];
    }

    protected function onMenuOptionSelected(string $option): void
    {
        $this->saveData('color', ucfirst($option));
        
        $summary = "✅ <b>Selección Completada</b>\n\n" .
                   "🎨 Color: " . ucfirst($option);
        
        $this->respond($summary);
    }
}
