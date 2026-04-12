<?php
namespace irwinlopez1023\Magma4telegram;
use SrvClick\Scurlv2\Response;
use SrvClick\Scurlv2\Scurl;
use Exception;

trait MagmaSend{

    private ?string $telegramBotUrl = null;
    private ?string $messageId = null;

    public function MagmaSetBotToken($token): void
    {
        $this->telegramBotUrl = "https://api.telegram.org/bot".$token;
    }

    public function getEndPoint(string $endpoint): string
    {
        return $this->telegramBotUrl."/".$endpoint;
    }

    public function getLastMessageId(): ?string
    {
        return $this->messageId;
    }

    public function getLastMessage(): ?array
    {
        return $this->lastMessage;
    }

    public function editLastMessage(string $newMessage, string $parseMode = 'html', $replyMarkup = null): Response
    {
        if ($this->messageId === null || !isset($this->lastMessage['chat']['id'])) {
            throw new Exception('No message to edit. Send a message first.');
        }

        return $this->editTelegramMessage(
            (string) $this->lastMessage['chat']['id'],
            $this->messageId,
            $newMessage,
            $parseMode,
            $replyMarkup
        );
    }

    private ?array $lastMessage = null;


    /**
     * @throws Exception
     */
    public function editTelegramMessage(string $chatId, string $messageId, string $newMessage, string $parseMode = 'html', $replyMarkup = null): Response
    {
        if (empty($chatId) || empty($messageId) || empty($newMessage)) {
            throw new Exception('Missing chatId, messageId or newMessage');
        }

        $data = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $newMessage,
            'parse_mode' => $parseMode
        ];
        
        if ($replyMarkup) {
            $data['reply_markup'] = is_array($replyMarkup) ? json_encode($replyMarkup) : $replyMarkup;
        }

        return $this->sendPostRequest('editMessageText', $data);
    }

    protected ?string $incomingMessageId = null;

    protected function  answerCallback(string $text, $buttons = null, string $parseMode = 'html'): void
    {
        if (isset($this->chatId) && isset($this->incomingMessageId)) {
            $this->editTelegramMessage(
                $this->chatId,
                $this->incomingMessageId,
                $text,
                $parseMode,
                $buttons
            );
        }
    }


    /**
     * @throws Exception
     */

    private function sendPostRequest(string $endpoint, array $data, bool $json = false): Response
    {
        $curl = new Scurl();
        $curl->url($this->getEndPoint($endpoint));
        
        if (Magma::getProxy() !== null) {
            $curl->proxy(Magma::getProxy());
        }
        
        if ($json) {
            $curl->headers(['Content-Type: application/json']);
            $curl->post()->parameters(json_encode($data));
        } else {
            $curl->post()->parameters($data);
        }
        $response = $curl->Send();

        if ($response->isOk()){
            $result = $response->json();
            if (isset($result['result']['message_id'])) {
                $this->messageId = $result['result']['message_id'];
            }
            $this->lastMessage = $result['result'] ?? null;
        }

        return $response;
    }
    /**
     * @throws Exception
     */
    public function sendTelegramMessage(string $chatId, ?string $message = null, string $parseMode = 'html', $replyMarkup = null): Response
    {
        if (empty($message) || empty($chatId)) {
            throw new Exception('Missing message or telegram chatId');
        }
        $data = [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => $parseMode
        ];
        if ($replyMarkup) {
            $data['reply_markup'] = is_array($replyMarkup) ? json_encode($replyMarkup) : $replyMarkup;
        }
        return $this->sendPostRequest('sendMessage', $data);
    }

    /**
     * @throws Exception
     */
    public function sendTelegramPhoto(string $chatId, string $photo, ?string $caption = null, string $parseMode = 'html'): Response
    {
        if (empty($chatId) || empty($photo)) {
            throw new Exception('Missing chatId or photo');
        }
        $data = [
            'chat_id' => $chatId,
            'photo' => $photo,
        ];
        if ($caption !== null) {
            $data['caption'] = $caption;
            $data['parse_mode'] = $parseMode;
        }
        return $this->sendPostRequest('sendPhoto', $data);
    }

    /**
     * @throws Exception
     */
    public function sendTelegramVideo(string $chatId, string $video, ?string $caption = null, string $parseMode = 'html'): Response
    {
        if (empty($chatId) || empty($video)) {
            throw new Exception('Missing chatId or video');
        }
        $data = [
            'chat_id' => $chatId,
            'video' => $video,
        ];
        if ($caption !== null) {
            $data['caption'] = $caption;
            $data['parse_mode'] = $parseMode;
        }
        return $this->sendPostRequest('sendVideo', $data);
    }
    /**
     * @throws Exception
     */
    public function sendTelegramDocument(string $chatId, string $document, ?string $caption = null, string $parseMode = 'html'): Response
    {
        if (empty($chatId) || empty($document)) {
            throw new Exception('Missing chatId or document');
        }
        $data = [
            'chat_id' => $chatId,
            'document' => $document,
        ];
        if ($caption !== null) {
            $data['caption'] = $caption;
            $data['parse_mode'] = $parseMode;
        }
        return $this->sendPostRequest('sendDocument', $data);
    }

    /**
     * @throws Exception
     */
    public function deleteTelegramMessage(string $chatId, string $messageId): Response
    {
        if (empty($chatId) || empty($messageId)) {
            throw new Exception('Missing chatId or messageId');
        }

        $data = [
            'chat_id' => $chatId,
            'message_id' => $messageId
        ];

        return $this->sendPostRequest('deleteMessage', $data);
    }

    /**
     * @throws Exception
     */
    public function createProgressBar(string $chatId, string $text = "Loading...", int $size = 10): ProgressBar
    {
        $response = $this->sendTelegramMessage($chatId, $text);
        if (!$response->isOk() || $this->messageId === null) {
            throw new Exception('Failed to send progress bar message');
        }
        return new ProgressBar($this, $chatId, $this->messageId, $size);
    }
}
