<?php
namespace Modules;

use irwinlopez1023\Magma4telegram\MagmaCommand;
use irwinlopez1023\Magma4telegram\MagmaSend;
use irwinlopez1023\Magma4telegram\ConversationManager;
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
            
            // Read the previous message ID directly from the JSON file
            $basePath = str_replace('\\', '/', realpath(__DIR__ . '/../../src/storage/conversations'));
            $storagePath = $basePath . '/chat_' . $chatId . '.json';
            $previousMsgId = null;
            
            if (file_exists($storagePath)) {
                $content = file_get_contents($storagePath);
                $data = json_decode($content, true);
                if ($data && isset($data['data']['_current_msg_id'])) {
                    $previousMsgId = $data['data']['_current_msg_id'];
                }
            }
            
            file_put_contents(__DIR__ . '/../../menu_debug.txt', "[" . date('H:i:s') . "] chatId: {$chatId}\n", FILE_APPEND);
            file_put_contents(__DIR__ . '/../../menu_debug.txt', "[" . date('H:i:s') . "] previousMsgId: " . ($previousMsgId ?? 'null') . "\n", FILE_APPEND);
            file_put_contents(__DIR__ . '/../../menu_debug.txt', "[" . date('H:i:s') . "] storagePath: {$storagePath}\n", FILE_APPEND);
            file_put_contents(__DIR__ . '/../../menu_debug.txt', "[" . date('H:i:s') . "] file_exists: " . (file_exists($storagePath) ? 'yes' : 'no') . "\n", FILE_APPEND);
            
            // Clear the JSON file directly
            if (file_exists($storagePath)) {
                unlink($storagePath);
            }
            
            $magma = new \irwinlopez1023\Magma4telegram\Magma($this->botToken);
            $conversation = new MenuDemoConversation($magma, $chatId);
            
            // Pass the previous message ID to destroy it
            $conversation->startWithDestroy($previousMsgId);
            
        } catch (Exception $e) {
            echo $e->getMessage() . "\n";
        }
    }
}
