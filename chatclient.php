<?php

declare(strict_types = 1);

namespace Chat;

require_once __DIR__."/chatprotocol.php";


// Клиентская часть сетевого чата
class ChatClient extends ChatProtocol {

    // Имя хоста сервера
    private $host = "";
    // Номер порта сервера
    private $port = 0;
    // Логин клиента
    private $login = "";
    // Сокет сервера
    private $server_socket = null;
    // Сокет клиента
    private $client_socket = null;


    // Создание клиента сетевого чата
    public function __construct(string $host="localhost", int $port=8080) {
        $this->host = $host;
        $this->port = $port;
        // Создание клиентского сокета
        $this->client_socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        // Соединение с сервером
        $this->server_socket = socket_connect($this->client_socket, $this->host, $this->port);
        if ($this->server_socket === false) {
            $this->abort("Не удалось соединиться с сервером !");
        }
    }

    // Закрытие клиента
    public function close() {
        $this->write(self::quit());
        socket_close($this->server_socket);
        socket_close($this->client_socket);
    }

    // Авторизация клиента на сервере
    public function authorization(string $login) : string {
        $this->write(self::login($login));
        $message = $this->read();
        list($_, $message_data) = self::parse($server_message);
        return $message_data;
    }

    // Отправка сообщения с текстом на сервер
    public function write_text(string $text) {
        $this->write(self::message($text));
    }

    // Отправка сообщения серверу
    private function write(string $message) {
        socket_write($this->server_socket, $message, self::MESSAGE_MAX_LENGTH);
    }

    // Получение сообщения от сервера
    private function read() : string {
        return socket_read($this->server_socket, self::MESSAGE_MAX_LENGTH);
    }

    // Аварийное завершение клиента
    private function abort(string $text) {
        if (!is_null($this->server_socket)) {
            socket_close($this->server_socket);
        }
        socket_close($this->client_socket);
        throw new Exception($text);
    }

    // Сообщение с логином клиента для авторизации на сервере
    private static function login(string $login) : string {
        return "LOGIN" . self::DATA_SEP . $login . self::DATA_END;
    }

}
