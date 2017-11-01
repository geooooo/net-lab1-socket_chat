<?php

declare(strict_types = 1);

namespace Chat;


// Протокол передачи сообщений чата
// Клиент и сервер общаются посредством передачи сообщений друг другу:
// MESSAGE_NAME [MESSAGE_SEPARATOR MESSAGE_DATA] MESSAGE_END
abstract class ChatProtocol {

    // Признак окончания данных в сообщении
    const DATA_END = "\0";
    // Разделитель между заголовком сообщения и данными
    const DATA_SEP = " ";


    // Сообщение от клиента или сервера
    protected static function message(string $data) : string {
        return "MESSAGE{self::DATA_SEP}$data{self::DATA_END}";
    }

    // Сообщение о выходе клиента или завершении работы сервера
    protected static function quit() : string {
        return "QUIT{self::DATA_END}";
    }

    // Парсинг сообщения
    protected static function parse(string $message) : array {
        list($message_name, $message_data) = explode(self::DATA_SEP, $message);
        $message_data[strlen($message_data)-1] = "";
        return [$message_name, $message_data];
    }

    // Передача информации об авторизации пользователя
    abstract static function login(string $data) : string;

}