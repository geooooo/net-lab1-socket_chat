<?php

declare(strict_types = 1);

namespace Chat;

require_once __DIR__."chatprotocol.php";



class ChatServer extends ChatProtocol {

    // Максимальное число подключающихся клиетов
    const CLIENT_MAX_COUNT = 100;


    // Имя хоста сервера
    private $host = "";
    // Номер порта сервера
    private $port = 0;
    // Сокет сервера
    private $server_socket = null;
    // Информация о клиентах
    // [
    //      "login" => ...,
    //      "socket" => ...,
    //      "TODO: thread"
    // ]
    private $clients = [];


    // Создание сервера
    public function __construct(string $host="localhost", int $port=8080) {
        $this->$host = $host;
        $this->$port = $port;
        // Создание серверного сокета
        $this->$server_socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($this->$server_socket === false) {
            $this->abort("Не удалось создать серверный сокет !");
        }
        // Привязка серверного сокета к хосту и порту
        if (socket_bind($this->$server_socket, $this->$host, $this->$port) === false) {
                $this->abort("Не удалось привязать имя хоста и порт к серверному сокету !");
        }
        $this->write_log("Сервер запущен ...");
    }

    // Закрытие сервера
    private function close() {
        $this->write_log("Закрытие соединений ...");
        foreach ($this->$clients as $client) {
            socket_close($client["socket"]);
            // TODO: Завершение thread
        }
        $this->write_log("Завершение работы сервера ...");
    }

    // Ожидание подключений клиентов
    private function waiting() {
        if (socket_listen($this->$server_socket, self::CLIENT_MAX_COUNT) === false) {
            $this->abort("Не удалось начать прослушивание входящих соединений !");
        }
        $this->write_log("Сервер готов к установке соединений ...");
        // TODO: Ожидание подключений клиентов
    }

    // Прослушивание и отправка сообщений клиенту
    private function listen(resource $client_socket) {
        // TODO: Прослушивание и отправка сообщений клиенту
    }

    // Отправка сообщения клиенту
    private function write(resource $client_socket, string $message) {
        socket_write($client_socket, $message, SELF::MESSAGE_MAX_LENGTH);
    }

    // Отправка сообщения всем клиентам
    private function write_all(string $message) {
        foreach ($this->$clients as $client) {
            $this->write($client["socket"], $message);
        }
    }

    // Логирование на сервере
    private function write_log(string $text) {
        print($text."\n");
    }

    // Получение сообщения от клиента
    private function read(resource $client_socket) : string {
        return socket_read($client_socket, SELF::MESSAGE_MAX_LENGTH);
    }

    // Добавление информации о клиенте
    private function append_client(resource $client_socket,
                                   resource $client_proccess,
                                   string   $client_login) {
        // TODO: Добавление информации о клиенте: thread
        $this->$clients[] = [
            "login"  => $client_login,
            "socket" => $client_socket
        ];
    }

    // Удаление информации о клиенте
    private function remove_client(string $client_login) {
        $this->$clients = array_filter($this->$clients, function ($client) {
            return $client->$login === $client_login;
        });
    }

    // Обработка сообщения от клиента
    private function proccess_message(resource $client_socket, string $message) {
        list($message_name, $message_data) = SELF::parse($message);
        switch ($message_name) {
            case "LOGIN":   proccess_message_login($client_socket, $message_data);
                            break;
            case "MESSAGE": proccess_message_message($client_socket, $message_data);
                            break;
            case "QUIT":    proccess_message_quit($client_socket, $message_data);
                            break;
            default:        $this->abort("Неизвестное сообщение от клиента !");
        }
    }

    // Обработка входящего от клиента сообщения авторизации
    private function proccess_message_login(resource $client_socket, string $data) {
        // TODO: Обработка входящего от клиента сообщения авторизации
    }

    // Обработка входящего от клиента сообщения с текстом
    private function proccess_message_message(resource $client_socket, string $data) {
        // TODO: Обработка входящего от клиента сообщения с текстом
    }

    // Обработка входящего от клиента сообщения о завершении работы
    private function proccess_message_quit(resource $client_socket, string $data) {
        // TODO: Обработка входящего от клиента сообщения о завершении работы
    }

    // Аварийное закрытие сервера и вывод сообщения
    private function abort(string $text) {
        $this->close();
        $this->write_log($text);
    }

    // Ответ клиенту на сообщение об авторизации
    private static function login(string $data) : string {
        return "LOGIN".SELF::DATA_SEP.$data.SELF::DATA_END;
    }

}
