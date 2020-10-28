<?php

/**
 * @author JuraSciix
 * @link https://vk.com/id370457723
 */

// Подключение файлов...
require "bot/exceptions.php";
require "bot/VKApi.php";
require "bot/LongPoll.php";
require "bot/Bot.php";

console("Запуск...");
new bot\Bot();

/**
 * Выводит сообщение в консоль с указанным префиксом и форматом даты.
 *
 * @param string $message
 * @param string|null $prefix
 * @param string $format
 */
function console(string $message, string $prefix = null, string $format = "H:i:s"): void{
    echo "[" . date($format) . ($prefix ? "/$prefix" : "") . "] $message" . PHP_EOL;
}