<?php
namespace Modules;

use irwinlopez1023\Magma4telegram\MagmaCommand;
use irwinlopez1023\Magma4telegram\MagmaSend;
use Exception;

class process extends MagmaCommand {
    use MagmaSend;

    protected string $command = "/process {task}";
    protected ?string $chatId = null;

    public function handle(): void
    {
        try {
            $taskName = $this->argument('task');
            $bar = $this->createProgressBar($this->chatId, "⏳ Starting the '{$taskName}' task...");
            sleep(1);
            $bar->update(25, "⚙️ Fetching required files...");
            sleep(2);
            $bar->update(70, "🗃️ Processing database records...");
            sleep(1);
            $bar->update(100, "✅ Task '{$taskName}' completed successfully!");
        } catch (Exception $e) {
            echo $e->getMessage() . "\n";
        }
    }
}
