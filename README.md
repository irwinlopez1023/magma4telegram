# Magma4Telegram

Magma4Telegram is a clean, modular, and intuitive routing framework for Telegram bots based on webhooks. Built for PHP developers who want an elegant way to structure their Telegram bot commands, handle callback queries, abstract away repetitive API calls, seamlessly manage multi-step conversations, and easily dispatch background jobs.

## Features

- **Command Routing**: Magma parses incoming webhook requests and routes them to a specific handler class.
- **Magic Arguments Extraction**: Define commands like `/info {name}` and Magma magically passes the argument to the handler.
- **Command Aliases**: Define multiple trigger words for the same command (e.g., `/ping`, `ping`, `pong` all trigger the same handler).
- **Conversations**: Easily build stateful, multi-step conversation flows that automatically capture and persist user input across different webhook requests.
- **Interactive Callbacks**: Map inline keyboard button clicks to specific methods inside your class seamlessly.
- **Builder Pattern for Keyboards**: Create inline keyboards easily via the `Keyboard` builder.
- **Dynamic Progress Bars**: Easily create and update visual progress bars within messages to keep users informed about long-running tasks.
- **Asynchronous Jobs**: Dispatch heavy tasks to run completely in the background without blocking the user or the webhook response.
- **Modular & Clean Architecture**: Keep your code clean by isolating commands, conversations, and jobs in their own respective classes.
- **Auto-Discovery**: Magma can automatically scan a directory and register all your command, conversation, and job classes dynamically without manual requires.

## Installation

```bash
composer require irwinlopez1023/magma4telegram
```

## Quick Start

Initialize Magma by providing your Telegram Bot Token. You can let Magma discover your classes automatically.

```php
<?php

require_once __DIR__."/vendor/autoload.php";

use irwinlopez1023\Magma4telegram\Magma;

try {
    // Automatically discover your app's structure
    Magma::autoDiscoverCommands(__DIR__ . '/Modules', 'Modules\\');
    Magma::autoDiscoverConversations(__DIR__ . '/Conversations', 'Conversations\\');
    Magma::autoDiscoverJobs(__DIR__ . '/Jobs', 'Jobs\\');

    // Instantiate Magma with your bot token
    $botToken = 'YOUR_TELEGRAM_BOT_TOKEN_HERE';
    $magma = new Magma($botToken);

} catch (Exception $exception) {
    echo $exception->getMessage();
}
```

## Creating a Basic Command

To create a command, make a new class that extends `MagmaCommand` and uses the `MagmaSend` trait (which provides helpers like `$this->sendTelegramMessage()`).

Define the command structure via the `protected string $command` property. Arguments inside `{}` are automatically extracted and accessible via `$this->argument('name')`.

```php
<?php
namespace Modules;

use irwinlopez1023\Magma4telegram\MagmaCommand;
use irwinlopez1023\Magma4telegram\MagmaSend;
use Exception;

class info extends MagmaCommand {
    use MagmaSend;

    // Define the command route and expected arguments
    protected string $command = "/info {name}";

    // The chatId is automatically injected by Magma
    protected ?string $chatId = null;

    public function handle(): void
    {
        try {
            // Retrieve argument magically and send a response
            $this->sendTelegramMessage($this->chatId, "Hello " . $this->argument('name'));
        } catch (Exception $e) {
            echo $e->getMessage() . "\n";
        }
    }
}
```

### Command Aliases

You can define aliases for a command so it responds to multiple trigger words without duplicating code. For example, `/ping` can also be triggered by `ping` or `pong`.

```php
<?php
namespace Modules;

use irwinlopez1023\Magma4telegram\MagmaCommand;
use irwinlopez1023\Magma4telegram\MagmaSend;

class ping extends MagmaCommand {
    use MagmaSend;

    protected static string $command = '/ping';
    protected static array $aliases = ['ping', 'pong'];
    protected ?string $chatId = null;

    public function handle(): void
    {
        $this->sendTelegramMessage($this->chatId, 'Pong!');
    }
}
```

This command responds to:
- `/ping` (the main command)
- `ping` (alias)
- `pong` (alias)

## Conversations (Multi-step flows)

If you need to collect multiple pieces of information from a user sequentially (e.g., asking for a name, then age, then confirming), you can use Magma's Conversation system.

### 1. Create a Conversation Class
Create a class extending `MagmaConversation`. Every step must point to the `next()` method that will handle the user's next message.

```php
<?php
namespace Conversations;

use irwinlopez1023\Magma4telegram\MagmaConversation;

class RegistrationConversation extends MagmaConversation
{
    public function start(): void
    {
        $this->ask("Hello! Let's get you registered. What is your name?");
        
        // The next incoming message will be passed to the 'askAge' method
        $this->next('askAge');
    }

    public function askAge(string $response): void
    {
        // Save the received data (the name) in the conversation state
        $this->saveData('name', $response);

        $this->ask("Nice to meet you, {$response}. How old are you?");
        
        // The next incoming message will be handled by 'finish'
        $this->next('finish');
    }

    public function finish(string $response): void
    {
        $name = $this->getData('name');
        $age = $response;

        $this->ask("Perfect! You've successfully registered.\nName: {$name}\nAge: {$age}");

        // End the conversation and clear the state
        $this->end();
    }
}
```

### 2. Trigger the Conversation from a Command
In any command, instantiate the conversation, passing the Magma instance and the Chat ID, and call `start()`.

```php
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
            // Initialize Magma again for the conversation instance
            $magma = new Magma($this->botToken);
            
            $conversation = new RegistrationConversation($magma, $this->chatId);
            $conversation->start();
            
        } catch (Exception $e) {
            echo $e->getMessage() . "\n";
        }
    }
}
```

## Multi-Level Menus

For complex menus with submenus and navigation, extend `MagmaMenu` instead of `MagmaConversation`.

**Key features:**
- Define menus as methods returning button arrays
- Navigate between menus with "back" button
- Automatic message editing (no spam)
- Previous menus are automatically deleted when starting a new one
- Stack-based navigation history

```php
<?php
namespace Conversations;

use irwinlopez1023\Magma4telegram\MagmaMenu;

class ColorMenuConversation extends MagmaMenu
{
    public function start(): void
    {
        $this->showMenu('main');
    }

    protected function menu_main(): array
    {
        return [
            ['text' => '🔴 Red', 'callback' => 'red'],
            ['text' => '🔵 Blue', 'callback' => 'blue'],
            ['text' => '🟢 Green', 'callback' => 'green'],
            ['text' => '🎨 More colors', 'callback' => 'more_colors'],
        ];
    }

    protected function menu_more_colors(): array
    {
        return [
            ['text' => '⚫ Black', 'callback' => 'black'],
            ['text' => '⚪ White', 'callback' => 'white'],
            ['text' => '🟠 Orange', 'callback' => 'orange'],
            ['text' => '🔙 Back', 'callback' => '<<back'],
        ];
    }

    protected function onMenuOptionSelected(string $option): void
    {
        $this->saveData('color', ucfirst($option));
        $this->ask("You selected: " . ucfirst($option));
        $this->closeMenu();
    }
}
```

**How it works:**
1. `$this->showMenu('main')` displays the menu defined in `menu_main()`
2. Button clicks are routed to `handleMenuResponse()`
3. `<<back` callback navigates to the previous menu
4. Other callbacks trigger `onMenuOptionSelected()`
5. When a new command is executed, the previous menu message is automatically deleted

**Menu array format:**
- `['text' => 'Button Label', 'callback' => 'unique_id']` — inline button
- `'<<back'` — special callback for back navigation

**Navigation methods:**
- `$this->showMenu('menu_name')` — display a menu
- `$this->goBack()` — go to previous menu in stack
- `$this->closeMenu()` — close menu and end conversation

**Menu command example:**
```php
<?php
namespace Modules;

use irwinlopez1023\Magma4telegram\MagmaCommand;
use irwinlopez1023\Magma4telegram\MagmaSend;
use Conversations\ColorMenuConversation;
use Exception;

class menu extends MagmaCommand {
    use MagmaSend;

    protected string $command = "/menu";
    protected ?string $chatId = null;

    public function handle(): void
    {
        try {
            $magma = new \irwinlopez1023\Magma4telegram\Magma($this->botToken);
            $conversation = new ColorMenuConversation($magma, (string) $this->chatId);
            $conversation->start();
        } catch (Exception $e) {
            error_log("Menu Command Error: " . $e->getMessage());
        }
    }
}
```

**Note:** Magma automatically handles menu cleanup. When a user executes any command while a menu is active, Magma deletes the previous menu message before processing the new command.


## Background Jobs (Asynchronous Processing)

When a command needs to process heavy tasks (like downloading files, querying an external API, or processing database records), doing it synchronously will block the webhook and slow down the bot.

Magma provides an elegant Background Jobs system using the `MagmaJob` abstract class and `$this->dispatchAsync()`.

### 1. Create a Job Class

Create a class that extends `MagmaJob`. This class will be executed entirely in the background.

```php
<?php
namespace Jobs;

use irwinlopez1023\Magma4telegram\MagmaJob;
use irwinlopez1023\Magma4telegram\MagmaSend;
use irwinlopez1023\Magma4telegram\ProgressBar;
use Exception;

class MyBackgroundJob extends MagmaJob {
    use MagmaSend;

    protected static bool $magmaRunnerLogging = true; // Enable logging (disabled by default)

    public function handle(array $payload): void {
        try {
            $chatId = $payload['chatId'];
            $messageId = $payload['messageId'];

            // To use MagmaSend within a Job, you must set the token from the injected Magma instance
            $this->MagmaSetBotToken($this->magma->getBotToken());

            // You can reconstruct a ProgressBar created by the command using fromIds()
            $progressBar = ProgressBar::fromIds($this, $chatId, $messageId, 15);

            // Perform your long-running task here...
            for ($i = 10; $i <= 100; $i += 10) {
                sleep(1); // Simulating heavy work
                $progressBar->update($i, "Processing task in background... ($i%)");
            }

            $this->editTelegramMessage(
                $chatId,
                $messageId,
                "? Asynchronous task successfully finished in background!"
            );
        } catch (Exception $e) {
            error_log("Error in MyBackgroundJob: " . $e->getMessage());
        }
    }
}
```

### 2. Dispatch the Job from a Command

Inside your command, call `$this->dispatchAsync(Job::class, $payload)`. The command will immediately finish and respond to Telegram, while the Job will keep running in the background.

```php
<?php
namespace Modules;

use irwinlopez1023\Magma4telegram\MagmaCommand;
use irwinlopez1023\Magma4telegram\MagmaSend;
use Jobs\MyBackgroundJob;

class test_job extends MagmaCommand {
    use MagmaSend;

    protected string $command = "/testjob";
    protected ?string $chatId = null;

    public function handle(): void {
        // If your Jobs are in custom namespaces, specify the file that boots the app (e.g. webhook.php)
        $this->setBootstrapPath(realpath(__DIR__ . '/../webhook.php'));

        $progressBar = $this->createProgressBar($this->chatId, "? Starting asynchronous task...");

        $payload = [
            'chatId' => $progressBar->getChatId(),
            'messageId' => $progressBar->getMessageId()
        ];

        // Dispatch the job and pass the payload
        $this->dispatchAsync(MyBackgroundJob::class, $payload);

        // The script continues immediately. We can send a fast response.
        $this->sendTelegramMessage($this->chatId, "?? The job is being processed in the background!");
    }
}
```

> **Note:** Logging is disabled by default. To enable it, add `protected static bool $magmaRunnerLogging = true;` to your Job class. Logs are written to `runner_log.txt`.

## Creating Interactive Commands (Inline Buttons)

If your command involves interactive inline keyboards, include the `Interactable` trait. This trait allows you to map specific button clicks (callback data) to methods in your class.

Define the `protected array $callbacks` to map a `callback_data` string to a method name.

```php
<?php
namespace Modules;

use irwinlopez1023\Magma4telegram\Keyboard;
use irwinlopez1023\Magma4telegram\MagmaCommand;
use irwinlopez1023\Magma4telegram\MagmaSend;
use irwinlopez1023\Magma4telegram\Interactable;
use Exception;

class buttons extends MagmaCommand {
    use MagmaSend, Interactable;

    protected string $command = "/button {something} {something}";
    protected ?string $chatId = null;

    // Map button's callback_data to local methods
    protected array $callbacks = [
        'ok'     => 'confirm',
        'cancel' => 'cancel'
    ];

    /**
     * @throws Exception
     */
    public function handle(): void
    {
        // Build the inline keyboard
        $botones = Keyboard::inline()
            ->row()->button('Confirm', 'ok')
            ->row()->button('Cancel', 'cancel')
            ->get();

        $this->sendTelegramMessage($this->chatId, "Choose an option:", "html", $botones);
    }

    public function confirm(): void
    {
        // $this->answerCallback() is provided by the Interactable trait.
        // It automatically edits the message to provide feedback and remove the buttons.
        $this->answerCallback("You have successfully confirmed the action!");
    }

    public function cancel(): void
    {
        $this->answerCallback("Operation cancelled. Everything is fine.");
    }
}
```

## The Fluent Keyboard Builder

The `Keyboard` class provides a fluent Builder Pattern to construct inline keyboards intuitively.

### Structure and Rows
You structure your keyboard by defining rows. Calling `->row()` creates a new line in the keyboard. Any `->button(...)` calls following a `->row()` will place those buttons side-by-side on that same line.

### Magic URL Detection
Magma's `Keyboard` class is smart. When adding a button via `->button('Text', 'Data')`, Magma inspects the second parameter. If the data is a valid URL, it automatically creates a Telegram URL button. If it's a standard string, it creates a `callback_data` button that triggers your class methods.

```php
$keyboard = Keyboard::inline()
    // First row: Two buttons side-by-side using callback_data
    ->row()
    ->button('Approve', 'approve_action')
    ->button('Decline', 'decline_action')

    // Second row: A single button spanning the whole line.
    // Because the second parameter is a valid URL, Magma creates a URL button!
    ->row()
    ->button('Visit our Website', 'https://example.com')
    ->get();
```

## Progress Bars

Magma makes it incredibly easy to provide visual feedback for long-running operations using dynamic progress bars.

To use this feature, your class just needs to use the `MagmaSend` trait. You can create a progress bar instance using `$this->createProgressBar()` and then seamlessly update it with new percentages and text via `$bar->update()`.

```php
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

            // 1. Initialize the Progress Bar
            // This sends the initial message and returns a ProgressBar instance
            $bar = $this->createProgressBar($this->chatId, "? Starting the '{$taskName}' task...");

            sleep(1); // Simulating work

            // 2. Update the Progress Bar with a percentage and an optional new text
            $bar->update(25, "?? Fetching required files...");

            sleep(2); // Simulating more work

            $bar->update(70, "??? Processing database records...");

            sleep(1); // Finalizing work

            // 3. Complete the task
            $bar->update(100, "? Task '{$taskName}' completed successfully!");

        } catch (Exception $e) {
            echo $e->getMessage() . "\n";
        }
    }
}
```

## Advanced: Framework Integration

Magma is fully compatible with popular frameworks like Laravel and Symfony.

Because Magma handles Async Jobs by booting a background PHP process, if you use Magma within Laravel, you should point `setBootstrapPath()` to a custom bridge file that boots the Laravel Kernel, rather than `public/index.php`. 

Check out the full [SKILL.md](.ai/skills/magma4telegram/SKILL.md) guidelines internally to see exactly how to write this bridge file and handle `artisan` properly inside your jobs.

## Advanced: Standalone Usage (MagmaSend Trait)

The `MagmaSend` trait is completely decoupled from the main routing logic. This means you can use it in any external class, background worker, or script outside the primary `Magma` lifecycle to send messages or documents.

**CRITICAL WARNING:**
Because Magma automatically injects the bot token when routing requests, using the trait *standalone* means the token is not automatically set. You **MUST** manually initialize the bot token by calling `$this->MagmaSetBotToken('YOUR_TOKEN');` before attempting to send any requests.

```php
<?php

use irwinlopez1023\Magma4telegram\MagmaSend;

class NotificationService {
    use MagmaSend;

    public function sendAlert(string $chatId, string $message): void
    {
        // You MUST manually set the token when using the trait standalone
        $this->MagmaSetBotToken('YOUR_TELEGRAM_BOT_TOKEN_HERE');

        $this->sendTelegramMessage($chatId, $message);
    }
}
```

## Available Helpers

Through the `MagmaSend` trait, your command classes inherit various tools to interact with Telegram:
- `$this->sendTelegramMessage(string $chatId, string $message, string $parseMode = 'html', $replyMarkup = null)`
- `$this->sendTelegramPhoto(string $chatId, string $photo, ?string $caption = null, string $parseMode = 'html')`
- `$this->sendTelegramVideo(string $chatId, string $video, ?string $caption = null, string $parseMode = 'html')`
- `$this->sendTelegramDocument(string $chatId, string $document, ?string $caption = null, string $parseMode = 'html')`
- `$this->editTelegramMessage(string $chatId, string $messageId, string $newMessage, string $parseMode = 'html')`
- `$this->deleteTelegramMessage(string $chatId, string $messageId)`
- `$this->createProgressBar(string $chatId, string $text = "Loading...", int $size = 10): ProgressBar`

The `Interactable` trait provides:
- `$this->answerCallback(string $text, $buttons = null, string $parseMode = 'html')` (Automatically uses the class's `$this->chatId` and `$this->incomingMessageId` to edit the interaction message).

## Proxy Support (Debugging)

For debugging network requests through a proxy (e.g., Charles Proxy, Mitmproxy), configure it in your `webhook.php`:

```php
use irwinlopez1023\Magma4telegram\Magma;

Magma::setProxy('http://127.0.0.1:8888');
```

To disable the proxy, simply don't call `setProxy()` or pass `null`.
