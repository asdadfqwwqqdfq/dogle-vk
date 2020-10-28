<?php

/**
 * @author JuraSciix
 * @link https://vk.com/id370457723
 */

namespace bot;

class LongPoll{

    public const AUTH_USER = 1; // User Long Poll. Подробнее: https://vk.com/dev/using_longpoll
    public const AUTH_GROUP = 2; // Bots Long Poll. Подробнее: https://vk.com/dev/bots_longpoll

    // Список событий для пользовательского сервера.
    // Более наглядная документация: https://github.com/danyadev/longpoll-doc
    public const USER_EVENTS = [
        0x02 => "flag_set",
        0x03 => "flag_remove",
        0x04 => "message_new",
        0x05 => "message_edit",
        0x06 => "message_input_read",
        0x07 => "message_output_read",
        0x08 => "friend_online",
        0x09 => "friend_offline",
        0x0a => "message_mention_read",
        0x0c => "message_mention",
        0x0d => "message_history_delete",
        0x12 => "message_snippet_add",
        0x13 => "message_cache_clear",
        // 0x33 => "message_conversation_edit",
        0x34 => "message_conversation_edit",
        0x3f => "message_typing_start",
        0x40 => "message_voicing_start",
        0x50 => "message_unreached_change",
        0x51 => "friend_offline_edit",
        0x72 => "message_mention_edit",
        0x73 => "message_call"
    ];

    /**
     * @var VKApi
     */
    private VKApi $api;

    /**
     * @var int
     */
    private int $authType;

    /**
     * @var array
     */
    private array $session = [];

    /**
     * @var int
     */
    private int $targetID = 0;

    /**
     * @see LongPoll::AUTH_USER
     * @see LongPoll::AUTH_GROUP
     *
     * @param VKApi $api
     * @param int $authType тип сессии. Необходимо для корректной обработки данных.
     */
    public function __construct(VKApi $api, int $authType){
        $this->api = $api;
        if ($authType < 1 || $authType > 2) {
            throw new \InvalidArgumentException("Unknown auth type");
        }
        $this->authType = $authType;
    }

    /**
     * @return int
     */
    public function getAuthType(): int{
        return $this->authType;
    }

    /**
     * @see VKApi::request()
     *
     * @link https://vk.com/dev/users.get
     * @link https://vk.com/dev/groups.getById
     *
     * @return int идентификатор бота.
     * @throws ApiException
     */
    public function getTargetID(): int{
        if ($this->targetID) return $this->targetID;

        switch ($this->authType) {
            case LongPoll::AUTH_USER: return ($this->targetID = $this->api->request("users.get")[0]["id"] ?? 0);
            case LongPoll::AUTH_GROUP: return ($this->targetID = $this->api->request("groups.getById")[0]["id"] ?? 0);
            default: return 0;
        }
    }

    /**
     * Подключается к серверу и сохраняет данные для получения событий.
     *
     * @see VKApi::request()
     * @see LongPoll::getTargetID()
     *
     * @link https://vk.com/dev/messages.getLongPollServer
     * @link https://vk.com/dev/groups.getLongPollServer
     *
     * @param array $params дополнительные параметры.
     * @throws ApiException
     */
    public function getLongPollServer(array $params = []): void{
        switch ($this->authType) {
            case self::AUTH_USER:
                $this->session = $this->api->request("messages.getLongPollServer", $params);
                break;
            case self::AUTH_GROUP:
                $params["group_id"] ??= $this->getTargetID();
                $this->session = $this->api->request("groups.getLongPollServer", $params);
                break;
        }
    }

    /**
     * @see LongPoll::url()
     * @see LongPollException::__construct()
     *
     * @param int $wait время ожидания.
     * @param int $mode формат данных.
     * @param int $version версия сервера.
     * @return array[] список новых событий.
     *
     * @throws LongPollException
     */
    public function listen(int $wait = 25, int $mode = 2, int $version = 3): array{
        if (!$this->session) {
            throw new \RuntimeException("Cannot listen events while there is no connection");
        }
        $params = ["act" => "a_check", "key" => @$this->session["key"], "ts" => @$this->session["ts"], "wait" => $wait];

        switch ($this->authType) {
            case self::AUTH_USER:
                $params["mode"] ??= $mode;
                $params["version"] ??= $version;
                $result = $this->url("https://" . @$this->session["server"], $params);
                break;
            case self::AUTH_GROUP:
                $result = $this->url(@$this->session["server"], $params);
                break;
            default: return [];
        }
        $json = json_decode($result, true);

        if (isset($json["failed"])) switch ($json["failed"]) {
            case 1:
                $this->session["ts"] = $json["ts"];
                return $this->listen($wait, $mode, $version);
            case 2: throw new LongPollException("The key has expired", 2);
            case 3: throw new LongPollException("Information has been lost", 3);
            default: throw new LongPollException("$version < {$json["min_version"]} or $version > {$json["max_version"]}", 4);
        } else {
            $this->session["ts"] = $json["ts"];

            if ($this->authType === self::AUTH_USER) {
                for ($updates = [], $i = 0; ($update =@ $json["updates"][$i]); ++$i) {
                    // Начиная с версии 5.103 данные сообщения находятся в поле 'message'.
                    $message =& $this->api->getVersion() >= 5.103 ? $updates[$i]["object"]["message"] : $updates[$i]["object"];

                    if ($type = @self::USER_EVENTS[$update[0]]) {
                        if (isset($update[1])) $message["id"] = $update[1];
                        if (isset($update[2])) $message["flags"] = $update[2];
                        if (isset($update[3])) $message["peer_id"] = $update[3];
                        if (isset($update[4])) $message["date"] = $update[4];
                        if (isset($update[5])) $message["text"] = $update[5];
                        if (isset($update[6]["from"])) $message["from_id"] = (int) $update[6]["from"];
                        if (isset($update[7])) $message["attachments"] = $update[7];
                        if (@$update[8]) $message["random_id"] = $update[8];
                        if (isset($update[9])) $message["conversation_message_id"] = $update[9];
                    }
                    $updates[$i]["type"] = $type;
                }
                $json["updates"] = $updates;
            }
            return $json["updates"];
        }
    }

    /**
     * @param string $url ссылка.
     * @param array $params параметры запроса.
     * @return string|null ответ от сервера.
     */
    private function url(string $url, array $params): ?string{
        $result = @file_get_contents("$url?" . http_build_query($params));

        if (!$result && ($error = error_get_last())) {
            error_clear_last();
            throw new \RuntimeException("Request error: {$error["message"]}");
        }
        return $result;
    }

    /**
     * Выводит информацию об ошибке от сервера.
     *
     * @see LongPollException
     *
     * @param LongPollException $e исключение, свидетельствующее об произошедшей ошибке.
     */
    public function error(LongPollException $e): void{
        console("{$e->getMessage()}: {$e->getCode()}", "LongPoll");
    }
}