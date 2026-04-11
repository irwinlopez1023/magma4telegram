<?php
namespace Modules;

use irwinlopez1023\Magma4telegram\MagmaCommand;
use irwinlopez1023\Magma4telegram\MagmaSend;
use Jobs\MyBackgroundJob;
use Exception;

class test_job extends MagmaCommand {
    use MagmaSend;

    protected string $command = "/testjob";
    protected ?string $chatId = null;

    public function handle(): void {
        try {
            $this->setBootstrapPath(realpath(__DIR__ . '/../webhook.php'));

            $progressBar = $this->createProgressBar($this->chatId, "⏳ Starting asynchronous task...");
            
            $payload = [
                'chatId' => $progressBar->getChatId(),
                'messageId' => $progressBar->getMessageId()
            ];
            
            $this->dispatchAsync(MyBackgroundJob::class, $payload);
            
            $this->sendTelegramMessage($this->chatId, "🚀 The command finished executing. The job is being processed in the background and will update the previous bar.");
        } catch (Exception $e) {
            error_log("Error in test_job: " . $e->getMessage());
        }
    }
}
