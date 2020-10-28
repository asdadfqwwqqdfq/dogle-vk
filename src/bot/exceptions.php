<?php

/**
 * @author JuraSciix
 * @link https://vk.com/id370457723
 */

namespace bot;

class ApiException extends \Exception{

    /**
     * Исключение, которое будет выброшено если VK API вернет ошибку.
     *
     * @param string|null $errorMessage
     * @param int $errorCode
     */
    public function __construct(string $errorMessage = null, int $errorCode = 0){
        parent::__construct($errorMessage, $errorCode);
    }
}

class LongPollException extends \Exception{

    /**
     * Исключение, которое будет выброшено если сервер вернет неудачу.
     *
     * @param string|null $failedMessage
     * @param int $failedCode
     */
    public function __construct(string $failedMessage = null, int $failedCode = 0){
        parent::__construct($failedMessage, $failedCode);
    }
}