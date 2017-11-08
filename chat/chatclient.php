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
    // Сокет клиента
    private $client_socket = null;
    // Был ли клиент авторизован
    private $is_authorized = false;
    // Буфер входящих сообщений от сервера
    private $message_buf = "";


    // Создание клиента сетевого чата
    public function __construct(string $host="localhost", int $port=8080) {
        $this->host = $host;
        $this->port = $port;
        // Создание клиентского сокета
        $this->client_socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        // Соединение с сервером
        if (socket_connect($this->client_socket, $this->host, $this->port) === false) {
            $this->abort("Не удалось соединиться с сервером !");
        }
        socket_set_nonblock($this->client_socket);
    }

    // Закрытие клиента
    public function __destruct() {
        if (!is_null($this->client_socket)) {
            $this->close();
        }
    }

    // Закрытие клиента
    public function close() {
        if (!is_null($this->client_socket)) {
            $this->write(self::quit());
            socket_close($this->client_socket);
            $this->client_socket = null;
        }
    }

    // Авторизация клиента на сервере
    public function authorization(string $login) : string {
        if ($this->is_authorized) {
            $this->abort("Попытка повторной авторизации !");
        }
        $this->write(self::login($login));
        while (!($server_message = $this->read())) ;
        list($message_name, $message_data) = self::parse($server_message);
        if ($message_name === "QUIT") {
            return null;
        }
        if ($message_data === "OK") {
            $this->is_authorized = true;
        }
        return $message_data;
    }

    // Отправка сообщения с текстом на сервер
    public function write_text(string $text) {
        if (!$this->is_authorized) return;
        // Не отправлять пустые сообщения
        if (empty($text)) {
            return;
        }
        $this->write(self::message($text));
    }

    // Получение сообщения с текстом от сервера
    public function read_text() {
        if (!$this->is_authorized) return null;
        $server_message = $this->read();
        list($message_name, $message_data) = self::parse($server_message);
        // Если пришло сообщение о завершении сервера
        if ($message_name === "QUIT") {
            return null;
        }
        return $message_data;
    }

    // Отправка сообщения серверу
    private function write(string $message) {
        @socket_write($this->client_socket, $message, self::MESSAGE_MAX_LENGTH);
        // Если сервер разорвал соединение
        $sle = socket_last_error($this->client_socket);
        if ($sle !== 0 and $sle !== 11) {
            $this->abort("Сервер недоступен ! " . "(". socket_strerror($sle) .")");
        }
    }

    // Получение сообщения от сервера
    private function read() {
        if (empty($this->message_buf)) {
            $this->message_buf = @socket_read($this->client_socket, self::MESSAGE_MAX_LENGTH);
            // Если сервер разорвал соединение
            $sle = socket_last_error($this->client_socket);
            if ($sle !== 0 and $sle !== 11) {
                $this->abort("Сервер недоступен ! " . "(". socket_strerror($sle) .")");
            }
        }
        // Извлечение первого сообщения
        $message = "";
        if (!empty($this->message_buf)) {
            $messages = array_slice(explode(self::DATA_END, $this->message_buf), 0, -1);
            $messages = array_map(function ($message) {
                return $message . self::DATA_END;
            }, $messages);
            $this->message_buf = implode("", array_slice($messages, 1));
            $message = $messages[0];
        }
        return $message;
    }

    // Аварийное завершение клиента
    private function abort(string $text) {
        socket_close($this->client_socket);
        throw new \Exception($text);
    }

    // Сообщение с логином клиента для авторизации на сервере
    protected static function login(string $login) : string {
        return "LOGIN" . self::DATA_SEP . $login . self::DATA_END;
    }

}
