<?php

declare(strict_types = 1);

namespace Chat;

require_once __DIR__."chatprotocol.php";


// Серверная часть сетевого чата
class ChatServer extends ChatProtocol {

    // Максимальное число подключающихся клиетов
    const CLIENT_MAX_COUNT = 100;


    // Singleton-экземпляр текущего класса
    private static $instance = null;
    // Имя хоста сервера
    private $host = "";
    // Номер порта сервера
    private $port = 0;
    // Сокет сервера
    private $server_socket = null;
    // Информация о клиентах
    // [
    //      "login"  => ...,
    //      "socket" => ...
    // ]
    private $clients = [];
    // Количество подключившихся клиентов
    private $client_count = 0;


    // Создание сервера сетевого чата
    public function create(string $host="localhost", int $port=8080) {
        if (is_null($this->$instance)) {
            self::$instance = new self($host, $port);
        }
        return self::$instance;
    }

    // Создание сервера
    private function __construct(string $host, int $port) {
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
        // Ожидание подключения клиентов
        $this->waiting();
    }

    // Закрытие сервера
    private function close() {
        $this->write_log("Закрытие соединений ...");
        foreach ($this->$clients as $client) {
            socket_close($client["socket"]);
        }
        $this->write_log("Завершение работы сервера ...");
    }

    // Ожидание подключений клиентов
    private function waiting() {
        if (socket_listen($this->$server_socket, self::CLIENT_MAX_COUNT) === false) {
            $this->abort("Не удалось начать прослушивание входящих соединений !");
        }
        $this->write_log("Сервер готов к установке соединений ...");
        $client_socket = socket_accept($this->server_socket);
        ++$this->$client_count;
        $this->listen($client_socket);
    }

    // Прослушивание и отправка сообщений клиенту
    private function listen(resource $client_socket) {
        $client_login = "";
        while (true) {
            $client_message = $this->read($client_socket);
            $client_login = $this->process_message($client_socket, $client_message, $client_login);
        }
    }

    // Отправка сообщения клиенту
    private function write(resource $client_socket, string $message) {
        socket_write($client_socket, $message, self::MESSAGE_MAX_LENGTH);
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
        return socket_read($client_socket, self::MESSAGE_MAX_LENGTH);
    }

    // Добавление информации о клиенте
    private function append_client(resource $client_socket,
                                   string   $client_login) {
        $this->$clients[] = [
            "login"  => $client_login,
            "socket" => $client_socket
        ];
    }

    // Удаление информации о клиенте
    private function remove_client(string $client_login) {
        $this->$clients = array_filter($this->$clients, function ($client) {
            if ($client["login"] === $client_login) {
                socket_close($client["socket"]);
                --$this->$client_count;
                return false;
            }
            return true;
        });
    }

    // Обработка сообщения от клиента и получение его логина
    private function proccess_message(resource $client_socket,
                                      string   $client_message,
                                      string   $client_login) : string {
        list($message_name, $message_data) = self::parse($client_message);
        switch ($message_name) {
            case "LOGIN":   $client_login = proccess_message_login($client_socket, $message_data);
                            break;
            case "MESSAGE": proccess_message_message($client_socket, $client_login, $message_data);
                            break;
            case "QUIT":    proccess_message_quit($client_socket, $client_login);
                            break;
            default:        $this->abort("Неизвестное сообщение от клиента: '$client_message' !");
        }
        return $client_login;
    }

    // Обработка входящего от клиента сообщения авторизации
    private function proccess_message_login(resource $client_socket,
                                            string   $login) : string {
        $login_pattern = '/^[\w_]+$/';
        $message = "";
        $result_login = "";
        if (!preg_match($login_pattern, $login)) {
            if ($this->is_login_exists($login)) {
                $message = self::login("EXISTS");
                $this->write_log("? Новый клиент попытался соединиться с уже занятым логином");
            } else {
                $message = self::login("OK");
                $this->append_client($client_socket, $client_login);
                $this->write_log("+ $client_login соединился");
                $result_login = $login;
            }
        } else {
            $message = self::login("BAD");
            $this->write_log("? Новый клиент попытался соединиться с неправильным логином");
        }
        $this->write($client_socket, $message);
        return $result_login;
    }

    // Обработка входящего от клиента сообщения с текстом
    private function proccess_message_message(resource $client_socket,
                                              string   $client_login,
                                              string   $text) {
        $this->write_all(self::message($text));
        $this->write_log("$client_login: $text");
    }

    // Обработка входящего от клиента сообщения о завершении работы
    private function proccess_message_quit(resource $client_socket,
                                           string   $client_login) {
        $this->write($client_socket, self::quit());
        $this->remove_client($client_login);
        $this->write_log("- $client_login отсоединился");
    }

    // Проверка существования клиента с заданым логином
    private function is_login_exists(string $login) : boolean {
        foreach ($this->$clients as $client) {
            if ($client["login"] === $login) {
                return true;
            }
        }
        return false;
    }

    // Аварийное закрытие сервера и вывод сообщения
    private function abort(string $text) {
        $this->close();
        $this->write_log($text);
    }

    // Ответ клиенту на сообщение об авторизации
    private static function login(string $data) : string {
        return "LOGIN".self::DATA_SEP.$data.self::DATA_END;
    }

}
