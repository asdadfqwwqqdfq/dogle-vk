<?php

/**
 * @author JuraSciix
 * @link https://vk.com/id370457723
 */

namespace bot;

class Bot{

    public const ACCESS_TOKEN = "2123cc87c080bfabb312acdfcfb1f97422f4b7868b846ac0fccdda87b32f6005e4cb6b4a7a9dd9b4cf487"; // Токен для бота.
    public const VERSION = 5.122; // Версия VK API.
    public const AUTH_TYPE = LongPoll::AUTH_USER; // Тип сессии.

    /**
     * Конструктор бота. Тут реализовано подключение к серверу и дальнейшая обработка событий.
     *
     * @see console()
     * @see LongPoll::getLongPollServer()
     * @see LongPoll::listen()
     * @see LongPoll::error()
     * @see LongPoll::AUTH_USER
     * @see LongPoll::AUTH_GROUP
     * @see VKApi::error()
     * @see Bot::update()
     */
    public function __construct(){
        $api = new VKApi(self::ACCESS_TOKEN, self::VERSION);
        $longPoll = new LongPoll($api, self::AUTH_TYPE);

        try{
            console("Подключение к серверу...");
            $longPoll->getLongPollServer();

            console("Бот запущен!");
            while (true) {
                try{
                    // Перебираем новые события и обрабатываем их.
                    foreach ($longPoll->listen() as $update) $this->update($api, $longPoll, $update);
                } catch (LongPollException $e) {
                    $longPoll->error($e);
                    break;
                }
            }
        } catch (ApiException $e) { // В случае ошибки просто перехватываем исключение и бот завершает свою работу.
            $api->error($e);
        }
        console("Бот выключен.");
    }

    /**
     * Обрабатывает новые события поодиночке.
     *
     * @see VKApi::sendMessage()
     *
     * @param VKApi $api
     * @param LongPoll $longPoll
     * @param array $update
     */
    private function update(VKApi $api, LongPoll $longPoll, array $update): void{
        switch ($update["type"]) {
            case "message_new": // Новое сообщение.
                $peerID = $update["object"]["message"]["peer_id"]; // Идентификатор беседы, в которой было отправлено сообщение.
                $message = $update["object"]["message"]["text"]; // Текст сообщения.

                if (mb_strtolower($message) === "/bot") {
                    if ($longPoll->getAuthType() === LongPoll::AUTH_GROUP) {
                        $api->sendMessage($peerID, "Я бот."); // Отправка сообщения в ту же беседу.
                    } else {
                        $api->sendMessage($peerID, "Я страничный бот.");
                    }
                }
                break;
        }
    }
}