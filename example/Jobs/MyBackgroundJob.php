<?php
namespace Jobs;

use irwinlopez1023\Magma4telegram\MagmaJob;
use irwinlopez1023\Magma4telegram\MagmaSend;
use irwinlopez1023\Magma4telegram\ProgressBar;
use Exception;

class MyBackgroundJob extends MagmaJob {
    use MagmaSend;

    protected static bool $magmaRunnerLogging = false;
    
    public function handle(array $payload): void {
        try {
            $chatId = $payload['chatId'];
            $messageId = $payload['messageId'];
            
            $this->MagmaSetBotToken($this->magma->getBotToken());
            
            $progressBar = ProgressBar::fromIds($this, $chatId, $messageId, 15);
            
            for ($i = 10; $i <= 100; $i += 10) {
                sleep(1);
                $progressBar->update($i, "Processing task in background... ($i%)");
            }
            
            $this->editTelegramMessage(
                $chatId, 
                $messageId, 
                "✅ Asynchronous task successfully finished in background!"
            );
        } catch (Exception $e) {
            error_log("Error in MyBackgroundJob: " . $e->getMessage());
        }
    }
}
