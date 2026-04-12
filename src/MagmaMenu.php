<?php

namespace irwinlopez1023\Magma4telegram;

use Exception;

abstract class MagmaMenu extends MagmaConversation
{
    private const MENU_STACK_KEY = '_menu_stack';
    private const CURRENT_MENU_KEY = '_current_menu';
    private const CURRENT_MESSAGE_ID_KEY = '_current_msg_id';
    private static ?string $logFile = null;

    private static function getLogFile(): string
    {
        if (self::$logFile === null) {
            self::$logFile = __DIR__ . '/../menu_log.txt';
        }
        return self::$logFile;
    }

    private static function menuLog(string $msg): void
    {
        file_put_contents(self::getLogFile(), "[" . date('Y-m-d H:i:s') . "] " . $msg . PHP_EOL, FILE_APPEND);
    }

    public function startWithDestroy(?string $previousMessageId = null): void
    {
        self::menuLog("startWithDestroy called with: " . ($previousMessageId ?? 'null'));
        $this->showMenu('main', null, true, $previousMessageId);
    }

    public function showMenu(string $menuName, ?string $message = null, bool $initializeStack = false, ?string $destroyPreviousMessageId = null): void
    {
        $methodName = 'menu_' . $menuName;
        if (!method_exists($this, $methodName)) {
            throw new Exception("Menu method '{$methodName}' does not exist");
        }

        $menuMessage = $message ?? $this->getMenuMessage($menuName);
        $keyboard = $this->buildMenuKeyboard($menuName);

        $currentMsgId = $this->getData(self::CURRENT_MESSAGE_ID_KEY);
        
        // If caller passes a messageId to destroy, do it now
        if ($destroyPreviousMessageId) {
            try {
                self::menuLog("Destroying previous message: {$destroyPreviousMessageId}");
                $this->deleteTelegramMessage($this->chatId, $destroyPreviousMessageId);
            } catch (\Throwable $e) {
                self::menuLog("Destroy error: " . $e->getMessage());
            }
        }
        
        self::menuLog("showMenu({$menuName}), currentMsgId: " . ($currentMsgId ?? 'null') . ", initStack: " . ($initializeStack ? 'true' : 'false'));
        
        if ($initializeStack) {
            // Delete the previous menu message if it exists (when starting fresh)
            if ($currentMsgId) {
                try {
                    self::menuLog("Deleting previous message: {$currentMsgId}");
                    $this->deleteTelegramMessage($this->chatId, $currentMsgId);
                } catch (\Throwable $e) {
                    self::menuLog("Delete error: " . $e->getMessage());
                }
            }
            $this->saveData(self::MENU_STACK_KEY, [$menuName]);
            $this->saveData(self::CURRENT_MENU_KEY, $menuName);
            $this->saveData(self::CURRENT_MESSAGE_ID_KEY, null);
            $currentMsgId = null;
        }
        
        if ($currentMsgId) {
            self::menuLog("Editing message {$currentMsgId}");
            try {
                $this->editTelegramMessage($this->chatId, $currentMsgId, $menuMessage, 'html', $keyboard);
            } catch (\Throwable $e) {
                self::menuLog("Edit error: " . $e->getMessage());
                $this->sendNewMenuMessage($menuName, $menuMessage, $keyboard);
            }
        } else {
            $this->sendNewMenuMessage($menuName, $menuMessage, $keyboard);
        }
        
        $this->next('handleMenuResponse');
    }

    private function sendNewMenuMessage(string $menuName, string $menuMessage, array $keyboard): void
    {
        self::menuLog("Sending new message for {$menuName}");
        $response = $this->sendTelegramMessage($this->chatId, $menuMessage, 'html', $keyboard);
        $result = $response->json();
        if (isset($result['result']['message_id'])) {
            $msgId = (string)$result['result']['message_id'];
            self::menuLog("Saved message_id: {$msgId}");
            $this->saveData(self::CURRENT_MESSAGE_ID_KEY, $msgId);
        }
    }

    public function handleMenuResponse(string $response): void
    {
        self::menuLog("handleMenuResponse: '{$response}'");
        $stack = $this->getMenuStack();
        self::menuLog("Stack: " . json_encode($stack));
        
        if ($response === '<<back') {
            self::menuLog("Back pressed, stack count: " . count($stack));
            if (count($stack) > 1) {
                array_pop($stack);
                $previousMenu = end($stack);
                self::menuLog("Going back to: {$previousMenu}");
                $this->saveData(self::MENU_STACK_KEY, $stack);
                $this->saveData(self::CURRENT_MENU_KEY, $previousMenu);
                $this->showMenu($previousMenu);
            } else {
                self::menuLog("Cannot go back, stack too short");
            }
            return;
        }

        $currentMenu = $this->getCurrentMenu();
        self::menuLog("Current menu: {$currentMenu}");
        $callbackKey = $this->findCallbackInMenu($currentMenu, $response);

        if ($callbackKey !== null) {
            self::menuLog("Found callback: {$callbackKey}");
            $menuMethodName = 'menu_' . $callbackKey;
            if (method_exists($this, $menuMethodName)) {
                self::menuLog("Navigating to submenu: {$callbackKey}");
                $stack[] = $callbackKey;
                $this->saveData(self::MENU_STACK_KEY, $stack);
                $this->saveData(self::CURRENT_MENU_KEY, $callbackKey);
                $this->showMenu($callbackKey);
                return;
            }

            $methodName = 'on_' . $callbackKey;
            if (method_exists($this, $methodName)) {
                $this->$methodName();
            } else {
                $this->onMenuOptionSelected($callbackKey);
            }
        } else {
            $this->onMenuResponse($response);
        }
    }

    private function setMenuState(string $menuName, bool $addToStack): void
    {
        $stack = $this->getMenuStack();
        if ($addToStack) {
            $stack[] = $menuName;
        }
        $this->saveData(self::MENU_STACK_KEY, $stack);
        $this->saveData(self::CURRENT_MENU_KEY, $menuName);
    }

    private function getCurrentMenu(): string
    {
        return $this->getData(self::CURRENT_MENU_KEY) ?? 'main';
    }

    private function getMenuStack(): array
    {
        return $this->getData(self::MENU_STACK_KEY) ?? [];
    }

    protected function getMenuMessage(string $menuName): string
    {
        return "Selecciona una opción:";
    }

    private function buildMenuKeyboard(string $menuName): array
    {
        $methodName = 'menu_' . $menuName;
        $keyboardData = $this->$methodName();

        $keyboard = [];
        $currentRow = [];

        foreach ($keyboardData as $item) {
            if ($item === 'row') {
                if (!empty($currentRow)) {
                    $keyboard[] = $currentRow;
                    $currentRow = [];
                }
                continue;
            }

            $text = $item['text'] ?? $item[0];
            $callback = $item['callback'] ?? $item[1] ?? $text;

            $currentRow[] = ['text' => $text, 'callback_data' => $callback];
        }

        if (!empty($currentRow)) {
            $keyboard[] = $currentRow;
        }

        return ['inline_keyboard' => $keyboard];
    }

    private function findCallbackInMenu(string $menuName, string $callbackData): ?string
    {
        $methodName = 'menu_' . $menuName;
        if (!method_exists($this, $methodName)) {
            return null;
        }
        
        $keyboardData = $this->$methodName();

        foreach ($keyboardData as $item) {
            if (is_array($item)) {
                $callback = $item['callback'] ?? $item[1] ?? null;
                if ($callback === $callbackData) {
                    return $callbackData;
                }
            }
        }

        return null;
    }

    protected function goBack(): void
    {
        $stack = $this->getMenuStack();
        if (count($stack) > 1) {
            array_pop($stack);
            $previousMenu = end($stack);
            $this->saveData(self::MENU_STACK_KEY, $stack);
            $this->saveData(self::CURRENT_MENU_KEY, $previousMenu);
            $this->showMenu($previousMenu);
        }
    }

    protected function closeMenu(): void
    {
        $this->saveData(self::MENU_STACK_KEY, []);
        $this->saveData(self::CURRENT_MENU_KEY, '');
        $this->saveData(self::CURRENT_MESSAGE_ID_KEY, null);
        $this->end();
    }

    protected function respond(string $message, string $parseMode = 'html'): void
    {
        $currentMsgId = $this->getData(self::CURRENT_MESSAGE_ID_KEY);
        
        if ($currentMsgId) {
            $this->editTelegramMessage($this->chatId, $currentMsgId, $message, $parseMode);
        } else {
            $this->ask($message);
        }
        
        $this->closeMenu();
    }

    protected function onMenuOptionSelected(string $option): void
    {
    }

    protected function onMenuResponse(string $response): void
    {
    }
}
