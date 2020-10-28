<?php

/**
 * @author JuraSciix
 * @link https://vk.com/id370457723
 */

namespace bot;

class VKApi{

    public const HOST = "https://api.vk.com/method/"; // Хост, на который будут отправляться все запросы.

    /**
     * @var string
     */
    private string $accessToken;

    /**
     * @var string
     */
    private string $version;

    /**
     * Данные о последней ошибке, которую вернул VK API.
     *
     * @var array
     */
    private array $lastError = [];

    /**
     * @param string $accessToken ключ доступа для действий от имени бота.
     * @param string $version версия VK API. Подробнее: https://vk.com/dev/versions
     */
    public function __construct(string $accessToken, string $version){
        $this->accessToken = $accessToken;
        $this->version = $version;
    }

    /**
     * @return string
     */
    public function getVersion(): string{
        return $this->version;
    }

    /**
     * Отправляет сообщение в указанную беседу.
     *
     * @see VKApi::request()
     * @see VKApi::error()
     *
     * @link https://vk.com/dev/messages.send
     *
     * @param int $peerID идентификатор беседы.
     * @param string|null $message содержимое сообщения.
     * @param array $params дополнительные параметры.
     * @return int идентификатор сообщения.
     */
    public function sendMessage(int $peerID, ?string $message, array $params = []): int{
        try{
            $params["random_id"] ??= rand(PHP_INT_MIN, PHP_INT_MAX); // Начиная с версии 5.90 данный параметр является ОБЯЗАТЕЛЬНЫМ.

            return $this->request("messages.send", [
                "peer_id" => $peerID,
                "message" => $message
            ] + $params);
        } catch (ApiException $e) {
            $this->error($e);
            return -1;
        }
    }

    /**
     * Создает запрос к VK API.
     *
     * @see ApiException::__construct()
     *
     * @link VKApi::HOST
     *
     * @param string $method название метода.
     * @param array $params параметры запроса.
     * @return mixed ответ от VK API в объекте 'response'.
     *
     * @throws ApiException
     */
    public function request(string $method, array $params = []){
        $params["access_token"] ??= $this->accessToken;
        $params["v"] ??= $this->version;

        $result = @file_get_contents(self::HOST . "$method?" . http_build_query($params));
        if (!$result && ($error = error_get_last())) {
            error_clear_last();
            throw new \RuntimeException("Request error: {$error["message"]}");
        }
        $json = json_decode($result, true);

        if (isset($json["error"])) {
            $this->lastError = $json["error"];
            throw new ApiException($this->lastError["error_msg"], $this->lastError["error_code"]);
        }
        return $json["response"] ?? $json;
    }

    /**
     * Выводит информацию об ошибке и последнем запросе.
     *
     * @see ApiException
     *
     * @param ApiException $error исключение, свидетельствующее об произошедшей ошибке.
     */
    public function error(ApiException $error): void{
        console("{$error->getMessage()}: {$error->getCode()}", "VKApi");

        if ($this->lastError) {
            for ($i = 0, $j = 1; $i < count($this->lastError["request_params"]); ++$i, ++$j) {
                $param = $this->lastError["request_params"][$i];
                console("[$j] {$param["key"]}: {$param["value"]}");
            }
        }
    }
}