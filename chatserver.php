<?php

declare(strict_types = 1);

namespace Chat;

require_once __DIR__."chatprotocol.php";



class ChatServer extends ChatProtocol {

    // Имя хоста сервера
    private $host;
    // Номер порта сервера
    private $port;


    // Создание сервера
    public function __construct(string $host="localhost", int $port=8080) {
        $this->$host = $host;
        $this->$port = $port;
        // TODO: Создание сервера
    }

    // Закрытие сервера
    private function close() {
        // TODO: Закрытие сервера
    }

    // Ожидание подключений клиентов
    private function waiting() {
        // TODO: Ожидание подключений клиентов
    }

    // Прослушивание и отправка сообщений клиенту
    private function listen(StdClass $client_socket) {
        // TODO: Прослушивание и отправка сообщений клиенту
    }

    // Отправка сообщения клиенту
    private function write(StdClass $client_socket, string $message) {
        // TODO: Отправка сообщения клиенту
    }

    // Отправка сообщения всем клиентам
    private function write_all(string $message) {
        // TODO: Отправка сообщения клиенту
    }

    // Логирование сообщения
    private function write_log(string $message) {
        // TODO: Логирование сообщения
    }

    // Получение сообщения от клиента
    private function read(StdClass $client_socket) : array {
        // TODO: Получение сообщения от клиента
    }

    // Добавление информации о клиенте
    private function append_client(StdClass $client_socket,
                                   StdClass $client_proccess,
                                   string   $client_login) {
        // TODO: Добавление информации о клиенте
    }

    // Удаление информации о клиенте
    private function remove_client(StdClass $client_login) {
        // TODO: Удаление информации о клиенте
    }

    // Получение логина клиента
    private function read_login(StdClass $client_socket) : string {
        // TODO: Получение логина клиента
    }

    // Обработка сообщения от клиента
    private function proccess_message(string $message) {
        // TODO: Обработка сообщения от клиента
    }

    // Ответ клиенту на сообщение об авторизации
    private function login(string $data) : string {
        // TODO: Ответ клиенту на сообщение об авторизации
    }

}
