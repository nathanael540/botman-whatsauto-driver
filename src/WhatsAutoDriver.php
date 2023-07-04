<?php

namespace BotMan\Drivers\WhatsAuto;

use BotMan\BotMan\Drivers\HttpDriver;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Incoming\IncomingMessage;
use BotMan\BotMan\Messages\Outgoing\Actions\Button;
use BotMan\BotMan\Messages\Outgoing\OutgoingMessage;
use BotMan\BotMan\Messages\Outgoing\Question;
use BotMan\BotMan\Users\User;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class WhatsAutoDriver extends HttpDriver
{
    protected $messages = [];

    protected $replies = [];

    protected $reply = "";

    const DRIVER_NAME = 'WhatsAuto';

    public function buildPayload(Request $request)
    {
        $this->payload = new ParameterBag((array) json_decode($request->getContent(), true));
        $this->event = $this->payload;
        $this->config = Collection::make($this->config->get('whatsauto', []));
    }

    public function matchesRequest()
    {
        return !is_null($this->event->get('sender')) || !is_null($this->event->get('message')) || !is_null($this->event->get('app'));
    }

    public function getMessages()
    {
        if (empty($this->messages)) {
            $message = $this->event->get('message');
            $userId = $this->event->get('sender');
            $this->messages = [new IncomingMessage($message, $userId, $userId, $this->payload)];
        }
        return $this->messages;
    }

    public function getUser(IncomingMessage $matchingMessage)
    {
        return new User($matchingMessage->getSender());
    }

    public function getConversationAnswer(IncomingMessage $message)
    {
        return Answer::create($message->getText())->setMessage($message)->setInteractiveReply(true);
    }

    public function buildServicePayload($message, $matchingMessage, $additionalParameters = [])
    {

        if ($message instanceof OutgoingMessage) {
            return $message->getText();
        }

        if ($message instanceof Question) {
            $text = $message->getText();

            foreach ($message->getActions() as $btn) {
                $text .= "\r\n*" . $btn['value'] . "* - " . $btn['name'];
            }

            return $text;
        }
    }

    public function sendPayload($payload)
    {
        $this->replies[] = $payload;
    }

    public function sendRequest($endpoint, array $parameters, IncomingMessage $matchingMessage)
    {
        // 
    }

    public function isConfigured()
    {
        return true;
    }
    protected function joinMessages()
    {
        return implode("\r\n", $this->replies);
    }

    public function messagesHandled()
    {
        $message = $this->joinMessages();

        // Reset replies
        $this->replies = [];

        (new JsonResponse(["reply" => $message]))->send();
    }

    public static function options(array $arrayOfString)
    {
        $options = [];

        for ($i = 0; $i < sizeof($arrayOfString); $i++) {
            $options[] = Button::create($arrayOfString[$i])->value($i);
        }

        return $options;
    }
}
