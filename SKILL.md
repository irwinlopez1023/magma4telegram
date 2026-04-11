# Skill: Magma4Telegram Framework

You are a specialist in **Magma4Telegram** (`irwinlopez1023/magma4telegram`), a modular, webhook-based routing framework for Telegram bots written in PHP. Use this skill whenever the user is building, debugging, or extending a Magma4Telegram bot.

---

## 1. Project Overview

Magma routes incoming Telegram webhook payloads to handler classes called **Commands**. Heavy work is delegated to **Jobs** that run asynchronously in the background. Interactive keyboards and progress bars are first-class citizens. Stateful, multi-step flows are handled via **Conversations**.

**Key design principles:**
- Every command is its own class file inside a directory (typically `Modules/`).
- Every background job is its own class inside another directory (typically `Jobs/`).
- Every conversation is its own class inside another directory (typically `Conversations/`).
- Magma injects contextual properties (`$chatId`, `$incomingMessageId`, arguments) automatically when a route matches.
- `MagmaSend` is the trait that provides all Telegram API helpers. It must be initialized with a bot token before use.

---

## 2. Bootstrap / Entry Point (`webhook.php`)

```php
<?php
require_once __DIR__ . "/vendor/autoload.php";

use irwinlopez1023\Magma4telegram\Magma;

try {
    // Auto-discover and register all app classes
    Magma::autoDiscoverCommands(__DIR__ . '/Modules', 'Modules\\');
    Magma::autoDiscoverConversations(__DIR__ . '/Conversations', 'Conversations\\');
    Magma::autoDiscoverJobs(__DIR__ . '/Jobs', 'Jobs\\');

    $magma = new Magma('YOUR_TELEGRAM_BOT_TOKEN_HERE');

} catch (Exception $exception) {
    echo $exception->getMessage();
}
```

- `autoDiscoverCommands(string $directory, string $namespace)` scans the folder and registers every command class it finds — no manual registration needed.
- `autoDiscoverConversations` and `autoDiscoverJobs` load their respective classes dynamically.
- Always wrap in `try-catch`.

---

## 3. Creating Commands

### Minimal structure

```php
<?php
namespace Modules;

use irwinlopez1023\Magma4telegram\MagmaCommand;
use irwinlopez1023\Magma4telegram\MagmaSend;
use Exception;

class info extends MagmaCommand {
    use MagmaSend;

    // Route: static text or with {argument} placeholders
    protected string $command = "/info {name}";

    // REQUIRED: declare chatId with null default — Magma injects it automatically
    protected ?string $chatId = null;

    public function handle(): void
    {
        try {
            $name = $this->argument('name');
            $this->sendTelegramMessage($this->chatId, "Hello, $name!");
        } catch (Exception $e) {
            echo $e->getMessage() . "\n";
        }
    }
}
```

### Rules
- Class filename must match the class name (e.g., `info.php` for `class info`).
- `$command` defines the route. Arguments in `{braces}` are extracted automatically.
- Access arguments inside `handle()` via `$this->argument('argName')`.
- Always declare `protected ?string $chatId = null;` — even if not using it, Magma needs the property to inject.
- Always wrap `handle()` body in `try-catch`.

### Enabling and disabling commands (`$enabled`)

`$enabled` does not exist by default and is not required. A command without this property works normally — Magma only checks for it if it has been explicitly declared.

Internally, Magma skips a command only when the property exists AND is `false`:

```php
if (array_key_exists('enabled', $props) && $props['enabled'] === false) {
    continue; // command is ignored
}
```

**Only add `$enabled` when you want to be able to toggle the command on or off.** When the user asks to "disable" or "activate" a command or button, declare or flip this property — do not delete the class or comment out `handle()`.

```php
class interactivo extends MagmaCommand {
    use MagmaSend, Interactable;

    protected string $command = "/interactivo";
    protected ?string $chatId = null;
    protected bool $enabled = false; // bot will not respond to /interactivo
}
```

Set to `true` to re-activate it, or remove the property entirely to leave the command always active.

### Command Aliases (`$aliases`)

Aliases allow a single command to respond to multiple trigger words without duplicating code. For example, `/ping` can also be triggered by `ping` or `pong`.

```php
class PingCommand extends MagmaCommand
{
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

**Rules:**
- Aliases are optional. If not defined, the command only responds to its main `$command`.
- Aliases are defined as a `static array $aliases = ['alias1', 'alias2'];`
- Aliases are checked against the exact input without the `/` prefix (e.g., `ping` matches, not `/ping`).
- If an alias matches, the command is executed using the same `handle()` method.

---

## 4. Conversations (Stateful Multi-Step Flows)

Magma supports multi-step interactive workflows to collect user data sequentially.

### Step 1 — Create a Conversation

```php
<?php
namespace Conversations;

use irwinlopez1023\Magma4telegram\MagmaConversation;

class RegistrationConversation extends MagmaConversation
{
    public function start(): void
    {
        $this->ask("Hello! Let's get you registered. What is your name?");
        // Point to the method that will handle the next message
        $this->next('askAge');
    }

    public function askAge(string $response): void
    {
        // $response contains the user's text message
        $this->saveData('name', $response);

        $this->ask("Nice to meet you, {$response}. How old are you?");
        $this->next('finish');
    }

    public function finish(string $response): void
    {
        $name = $this->getData('name');
        $age = $response;

        $this->ask("Perfect! You've successfully registered.\nName: {$name}\nAge: {$age}");
        
        // Clean up the session data
        $this->end();
    }
}
```

### Step 2 — Start the Conversation from a Command

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
            // Re-instantiate Magma passing the token to load conversation dependencies
            $magma = new Magma($this->botToken);
            
            $conversation = new RegistrationConversation($magma, $this->chatId);
            $conversation->start();
            
        } catch (Exception $e) {
            echo $e->getMessage() . "\n";
        }
    }
}
```

---

## 5. MagmaSend Helpers (Available in Commands, Jobs, and Conversations)

Include the trait with `use MagmaSend;`. All methods below are available via `$this->`.

**`sendTelegramMessage(string $chatId, string $message, string $parseMode = 'html', $replyMarkup = null): void`**
Send a plain text message. Pass a keyboard array as `$replyMarkup` to attach buttons.

**`sendTelegramPhoto(string $chatId, string $photo, ?string $caption = null, string $parseMode = 'html'): void`**
Send a photo by file_id or URL.

**`sendTelegramVideo(string $chatId, string $video, ?string $caption = null, string $parseMode = 'html'): void`**
Send a video by file_id or URL.

**`sendTelegramDocument(string $chatId, string $document, ?string $caption = null, string $parseMode = 'html'): void`**
Send a file/document by file_id or URL.

**`editTelegramMessage(string $chatId, string $messageId, string $newMessage, string $parseMode = 'html'): void`**
Edit an existing message by its ID.

**`createProgressBar(string $chatId, string $text = "Loading...", int $size = 10): ProgressBar`**
Sends the initial progress bar message and returns a `ProgressBar` instance for subsequent updates.

> **Standalone use warning:** If `MagmaSend` is used outside a routed execution (e.g., in a plain service class), the bot token is NOT injected automatically. You MUST call `$this->MagmaSetBotToken('YOUR_TOKEN')` before any send method. The same applies inside Jobs.

---

## 6. Progress Bars (Synchronous)

Use when the task fits within the webhook response time (~1-2 seconds).

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

            // Creates the message and returns a ProgressBar instance
            $bar = $this->createProgressBar($this->chatId, "Starting '$taskName'...");

            sleep(1);
            $bar->update(25, "Fetching required files...");

            sleep(2);
            $bar->update(70, "Processing database records...");

            sleep(1);
            $bar->update(100, "Task '$taskName' completed successfully!");

        } catch (Exception $e) {
            echo $e->getMessage() . "\n";
        }
    }
}
```

**ProgressBar API:**
- `$bar->update(int $percent, string $text)` — edits the original message with a new percentage and label.
- `$bar->getChatId(): string` — returns the chat ID associated with the bar.
- `$bar->getMessageId(): string` — returns the message ID, needed to pass to async jobs.
- `ProgressBar::fromIds($context, $chatId, $messageId, $size)` — reconstructs a bar inside a Job from stored IDs.

---

## 7. Asynchronous Background Jobs

Use for any task that may take more than 1-2 seconds (API calls, file processing, DB queries, etc.).

### Step 1 — Dispatch from a Command

```php
<?php
namespace Modules;

use irwinlopez1023\Magma4telegram\MagmaCommand;
use irwinlopez1023\Magma4telegram\MagmaSend;
use Jobs\MyBackgroundJob;
use Exception;

class test_job extends MagmaCommand {
    use MagmaSend;

    protected string $command = "/test_job";
    protected ?string $chatId = null;

    public function handle(): void
    {
        try {
            // REQUIRED: tell the background runner how to bootstrap the app
            $this->setBootstrapPath(realpath(__DIR__ . '/../webhook.php'));

            // Create the progress bar and capture its IDs
            $bar = $this->createProgressBar($this->chatId, "Background task starting...");

            // Dispatch the job — execution returns immediately to Telegram
            $this->dispatchAsync(MyBackgroundJob::class, [
                'chatId'    => $bar->getChatId(),
                'messageId' => $bar->getMessageId(),
            ]);

        } catch (Exception $e) {
            echo $e->getMessage() . "\n";
        }
    }
}
```

### Step 2 — Implement the Job (`Jobs/MyBackgroundJob.php`)

```php
<?php
namespace Jobs;

use irwinlopez1023\Magma4telegram\MagmaJob;
use irwinlopez1023\Magma4telegram\MagmaSend;
use irwinlopez1023\Magma4telegram\ProgressBar;
use Exception;

class MyBackgroundJob extends MagmaJob {
    use MagmaSend;

    public function handle(array $payload): void
    {
        try {
            // REQUIRED in every Job: initialize the bot token manually
            $this->MagmaSetBotToken($this->magma->getBotToken());

            // Reconstruct the progress bar from the IDs passed in the payload
            $bar = ProgressBar::fromIds($this, $payload['chatId'], $payload['messageId'], 15);

            sleep(2);
            $bar->update(50, "Halfway there...");

            sleep(3);
            $bar->update(100, "Finished in background!");

        } catch (Exception $e) {
            error_log("MyBackgroundJob error: " . $e->getMessage());
        }
    }
}
```

**Job rules:**
- Always extend `MagmaJob`.
- Always call `$this->MagmaSetBotToken($this->magma->getBotToken())` at the top of `handle()`.
- Always use `error_log()` (not `echo`) for errors — there is no HTTP response to write to.
- `$this->magma` is injected automatically by the framework.

**Runner Logging:**
- Logging is disabled by default to avoid filling up disk space on high-traffic bots.
- To enable logging, add `protected static bool $magmaRunnerLogging = true;` to your Job class.
- When enabled, logs are written to `runner_log.txt` in the project root.

---

## 8. Inline Keyboards

```php
use irwinlopez1023\Magma4telegram\Keyboard;

$keyboard = Keyboard::inline()
    ->row()
    ->button('Approve', 'approve_action')
    ->button('Decline', 'decline_action')
    ->row()
    ->button('Visit our Website', 'https://example.com') // URL detected automatically
    ->get(); // Always finish with ->get()

$this->sendTelegramMessage($this->chatId, "Choose an option:", 'html', $keyboard);
```

- `Keyboard::inline()` — creates an inline keyboard (attached to a message).
- `Keyboard::reply()` — creates a reply keyboard (shown at the bottom of the chat).
- Chain `->row()` to start a new row, then `->button($label, $callbackDataOrUrl)` for each button.
- Always end the chain with `->get()` to retrieve the final keyboard array.
- If the second parameter of `->button()` is a valid URL, Magma automatically creates a URL button.

---

## 9. Interactive Callbacks (`Interactable` trait)

Use in command classes that need to respond to inline button presses.

```php
<?php
namespace Modules;

use irwinlopez1023\Magma4telegram\MagmaCommand;
use irwinlopez1023\Magma4telegram\MagmaSend;
use irwinlopez1023\Magma4telegram\Interactable;
use irwinlopez1023\Magma4telegram\Keyboard;

class menu extends MagmaCommand {
    use MagmaSend, Interactable;

    protected string $command = "/menu";
    protected ?string $chatId = null;

    // Map callback_data strings to method names in this class
    protected array $callbacks = [
        'approve_action' => 'onApprove',
        'decline_action' => 'onDecline',
    ];

    public function handle(): void
    {
        $keyboard = Keyboard::inline()
            ->row()
            ->button('Approve', 'approve_action')
            ->button('Decline', 'decline_action')
            ->get();

        $this->sendTelegramMessage($this->chatId, "Choose:", 'html', $keyboard);
    }

    public function onApprove(): void
    {
        // answerCallback uses $this->chatId and $this->incomingMessageId automatically
        $this->answerCallback("Approved!");
    }

    public function onDecline(): void
    {
        $this->answerCallback("Declined.");
    }
}
```

**Interactable API:**
- `protected array $callbacks` maps each `callback_data` string to a method name in the same class.
- `$this->answerCallback(string $text, $buttons = null, string $parseMode = 'html')` edits the original message using `$this->chatId` and `$this->incomingMessageId` (both auto-injected by Magma).

---

## 10. Standalone MagmaSend Usage

`MagmaSend` is decoupled from routing and can be used in any plain PHP class (e.g., a notification service, a cron script):

```php
<?php
use irwinlopez1023\Magma4telegram\MagmaSend;

class NotificationService {
    use MagmaSend;

    public function sendAlert(string $chatId, string $message): void
    {
        // MUST call this manually — no Magma routing = no automatic token injection
        $this->MagmaSetBotToken('YOUR_TELEGRAM_BOT_TOKEN_HERE');

        $this->sendTelegramMessage($chatId, $message);
    }
}
```

---

## 11. Framework Integration (Laravel, Symfony, Slim, etc.)

**When Magma is used inside an existing PHP framework, ALWAYS follow that framework's conventions.** Do not generate standalone files or raw PHP if the project has a framework structure. Adapt every Magma concept to fit naturally.

---

### 11.1 Detect the framework first

Before writing any code, inspect the project structure and identify the host framework:

- `artisan` file + `app/` + `routes/web.php` present -> **Laravel**
- `symfony.lock` or `config/services.yaml` present -> **Symfony**
- `composer.json` requiring `slim/slim` -> **Slim**
- `public/index.php` with no framework markers -> **Plain PHP**

Generate code appropriate to that context. **Never mix conventions** (e.g., do not create a raw `webhook.php` at the root if the project uses Laravel routes).

---

### 11.2 Laravel

#### The environment problem

Magma's async system works by spawning a background CLI process (`Runner.php`). That process has **no access to Laravel** (Eloquent, Log, Config, Service Providers) unless Laravel is explicitly booted inside it. Pointing `setBootstrapPath()` to `public/index.php` does NOT work — that file is the HTTP entry point, not a CLI bootstrapper.

#### Step 1 — Create the bridge file (`app/Telegram/magma_bootstrap.php`)

This file is the glue between Magma's background runner and Laravel. Create it once per project:

```php
<?php
/**
 * Magma4Telegram & Laravel Bridge
 * Boots the Laravel environment for background CLI processes.
 */

use Illuminate\Contracts\Console\Kernel;
use irwinlopez1023\Magma4telegram\Magma;

// 1. Load Composer autoloader
require __DIR__ . '/../../vendor/autoload.php';

// 2. Boot the Laravel application
$app = require_once __DIR__ . '/../../bootstrap/app.php';

// 3. Boot the Console Kernel — enables Log, Config, DB, and all Service Providers
$app->make(Kernel::class)->bootstrap();

// 4. Register classes so the Runner can find them
Magma::autoDiscoverCommands(app_path('Telegram/Commands'), 'App\\Telegram\\Commands\\');
Magma::autoDiscoverConversations(app_path('Telegram/Conversations'), 'App\\Telegram\\Conversations\\');
Magma::autoDiscoverJobs(app_path('Telegram/Jobs'), 'App\\Telegram\\Jobs\\');
```

#### Step 2 — Point `setBootstrapPath()` to the bridge

In every Command that dispatches an async Job, point to the bridge — never to `public/index.php`:

```php
$this->setBootstrapPath(app_path('Telegram/magma_bootstrap.php'));
```

Full example command:

```php
<?php
namespace App\Telegram\Commands;

use irwinlopez1023\Magma4telegram\MagmaCommand;
use irwinlopez1023\Magma4telegram\MagmaSend;
use App\Telegram\Jobs\MyAsyncJob;

class MyCommand extends MagmaCommand {
    use MagmaSend;

    protected string $command = "/procesar";
    protected ?string $chatId = null;

    public function handle(): void
    {
        try {
            $this->setBootstrapPath(app_path('Telegram/magma_bootstrap.php'));

            $bar = $this->createProgressBar($this->chatId, "Processing...");

            $this->dispatchAsync(MyAsyncJob::class, [
                'chatId'    => $bar->getChatId(),
                'messageId' => $bar->getMessageId(),
            ]);
        } catch (\Exception $e) {
            \Log::error($e->getMessage());
        }
    }
}
```

#### Step 3 — Webhook route

Register in `routes/api.php` (API-only bots) or `routes/web.php` (mixed apps):

```php
use App\Http\Controllers\TelegramController;

Route::post('/telegram/webhook', [TelegramController::class, 'handle']);
```

Exclude from CSRF. In **Laravel 11+** (`bootstrap/app.php`):

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: [
        'api/telegram/webhook',
    ]);
})
```

In **Laravel 10 and below** (`app/Http/Middleware/VerifyCsrfToken.php`):

```php
protected $except = [
    'telegram/webhook',
];
```

#### Step 4 — Controller (`app/Http/Controllers/TelegramController.php`)

```php
<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use irwinlopez1023\Magma4telegram\Magma;

class TelegramController extends Controller
{
    public function handle(Request $request): void
    {
        try {
            Magma::autoDiscoverCommands(app_path('Telegram/Commands'), 'App\\Telegram\\Commands\\');
            Magma::autoDiscoverConversations(app_path('Telegram/Conversations'), 'App\\Telegram\\Conversations\\');
            Magma::autoDiscoverJobs(app_path('Telegram/Jobs'), 'App\\Telegram\\Jobs\\');
            
            new Magma(config('services.telegram.token'));
        } catch (\Exception $e) {
            \Log::error('Magma error: ' . $e->getMessage());
        }
    }
}
```

- Use `config('services.telegram.token')` — never hardcode the token.
- Use `\Log::error()` instead of `error_log()`.
- Use `app_path()` and `base_path()` helpers, never raw `__DIR__` concatenation.

#### File placement in Laravel

| Magma concept | Location | Namespace |
| --- | --- | --- |
| Commands | `app/Telegram/Commands/` | `App\Telegram\Commands` |
| Conversations | `app/Telegram/Conversations/` | `App\Telegram\Conversations` |
| Jobs | `app/Telegram/Jobs/` | `App\Telegram\Jobs` |
| Bridge | `app/Telegram/magma_bootstrap.php` | (no namespace, plain PHP) |
| Services | `app/Services/` | `App\Services` |

#### PSR-4 note

`App\\` already covers all subdirectories under `app/` in Laravel, so no extra `composer.json` entries are needed for `app/Telegram/`. Only add entries if placing files outside `app/`, then run `composer dump-autoload`.

#### Token config

`config/services.php`:

```php
'telegram' => [
    'token' => env('TELEGRAM_BOT_TOKEN'),
],
```

`.env`:

```
TELEGRAM_BOT_TOKEN=your_token_here
```

#### OS compatibility note

Magma's `Runner.php` automatically detects the OS: it uses `start /B` on Windows (`WINNT`) and `&` on Linux/macOS to spawn background processes. No manual configuration is needed, but be aware of this if debugging async dispatch on different environments.

#### IMPORTANT — Magma Jobs are NOT Laravel Queue jobs

Magma's async system (`dispatchAsync` + `MagmaJob`) is completely independent from Laravel's queue system. **Do NOT suggest or require any of the following** when working with Magma async jobs:

- `php artisan queue:listen`
- `php artisan queue:work`
- Laravel Horizon
- Any `Queue::push()` or `Bus::dispatch()` calls
- Any `Illuminate\Contracts\Queue` interfaces

Magma spawns its own background process via `Runner.php` directly — no queue driver, no worker daemon, and no supervisor configuration is needed for Magma's jobs to work. If the user is using Laravel's own queue system for something else in the same project, that is unrelated to Magma.

---

### 11.3 Symfony

#### Route (`config/routes.yaml`)

```yaml
telegram_webhook:
  path: /telegram/webhook
  controller: App\Controller\TelegramController::handle
  methods: [POST]
```

Or via PHP attribute directly on the controller method:

```php
#[Route('/telegram/webhook', methods: ['POST'])]
```

#### Controller

```php
<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use irwinlopez1023\Magma4telegram\Magma;

class TelegramController extends AbstractController
{
    #[Route('/telegram/webhook', methods: ['POST'])]
    public function handle(): Response
    {
        try {
            $basePath = $this->getParameter('kernel.project_dir') . '/src/Telegram';
            
            Magma::autoDiscoverCommands($basePath . '/Commands', 'App\\Telegram\\Commands\\');
            Magma::autoDiscoverConversations($basePath . '/Conversations', 'App\\Telegram\\Conversations\\');
            Magma::autoDiscoverJobs($basePath . '/Jobs', 'App\\Telegram\\Jobs\\');
            
            new Magma($this->getParameter('app.telegram_token'));
        } catch (\Exception $e) {
            error_log('Magma error: ' . $e->getMessage());
        }

        return new Response('', 200);
    }
}
```

#### File placement in Symfony

| Magma concept | Location | Namespace |
| --- | --- | --- |
| Commands | `src/Telegram/Commands/` | `App\Telegram\Commands` |
| Conversations | `src/Telegram/Conversations/` | `App\Telegram\Conversations` |
| Jobs | `src/Telegram/Jobs/` | `App\Telegram\Jobs` |

---

### 11.4 Slim

```php
$app->post('/telegram/webhook', function ($request, $response) {
    try {
        Magma::autoDiscoverCommands(__DIR__ . '/../src/Commands', 'App\\Commands\\');
        Magma::autoDiscoverConversations(__DIR__ . '/../src/Conversations', 'App\\Conversations\\');
        Magma::autoDiscoverJobs(__DIR__ . '/../src/Jobs', 'App\\Jobs\\');
        
        new Magma($_ENV['TELEGRAM_BOT_TOKEN']);
    } catch (Exception $e) {
        error_log($e->getMessage());
    }
    return $response->withStatus(200);
});
```

---

### 11.5 General rules for any framework

- **Never hardcode the bot token.** Use environment variables (`.env`, `$_ENV`, or framework config).
- **Never create a `webhook.php` at the project root** if the framework has a router. Always register a POST route through the framework.
- **Namespace commands and jobs** under the project's existing PSR-4 root. Only add new `composer.json` autoload entries if the directory falls outside the existing mappings, then run `composer dump-autoload`.
- **Use the framework's logger** (`\Log::error()` in Laravel, injected `LoggerInterface` in Symfony) instead of bare `error_log()` wherever possible.
- **Use the framework's path helpers** (`app_path()`, `base_path()`, `kernel.project_dir`) instead of raw `__DIR__` concatenation.
- **`setBootstrapPath()`** must point to the framework's actual entry point (`public/index.php`), not a Magma-specific file.

---

## 12. Quick Reference

| Rule | Detail |
| --- | --- |
| Namespace | Always `use irwinlopez1023\Magma4telegram\...` |
| Commands | Must extend `MagmaCommand` |
| Jobs | Must extend `MagmaJob` |
| Conversations | Must extend `MagmaConversation` |
| chatId | Always declare `protected ?string $chatId = null;` in every command class |
| handle() | Always wrap body in `try-catch` |
| Job errors | Use `error_log()` or framework logger, never `echo` |
| Token in Jobs | Call `$this->MagmaSetBotToken($this->magma->getBotToken())` at the top of `handle()` |
| Token standalone | Call `$this->MagmaSetBotToken('TOKEN')` before any send in non-routed classes |
| Bootstrap path | In plain PHP point to `webhook.php`. In Laravel point to `app/Telegram/magma_bootstrap.php` (the bridge). Never use `public/index.php` |
| Framework routes | Always use the framework's router for the webhook, never a raw PHP file at the root |
| Token storage | Never hardcode the token; use `.env` and framework config |
| File placement | Follow the framework's directory conventions |
| PSR-4 | Add `composer.json` entries and run `composer dump-autoload` only if placing files outside existing mapped directories |
| Logging | Use the framework's logger instead of bare `error_log()` when inside a framework |
| Keyboard chain | Always end with `->get()` |
| Parse mode | Default is `'html'` across all send methods |