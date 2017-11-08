<?php

declare(strict_types = 1);

namespace Chat;

require_once __DIR__."/chatprotocol.php";



// Серверная часть сетевого чата
class ChatServer extends ChatProtocol {

    // Максимальное число подключающихся клиетов
    const CLIENT_MAX_COUNT = 100;
    // Размер разделяемой памяти в байтах
    const SHM_SIZE = 100;


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
    //      "login"  => логин клиента
    //      "socket" => сокет клиента
    //      "pid"    => pid дочернего процесса, прослушивающего сокет
    // ]
    private $clients = [];
    // Количество подключившихся клиентов
    private $client_count = 0;
    // Ключ для доступа к разделяемой памяти
    private $shm_key = null;


    // Создание singleton-сервера сетевого чата
    public static function create(string $host="localhost", int $port=8080) {
        if (is_null(self::$instance)) {
            self::$instance = new self($host, $port);
        }
    }

    // Создание сервера
    private function __construct(string $host, int $port) {
        // Назначение хоста и порта сервера
        $this->host = $host;
        $this->port = $port;
        // Создание серверного сокета
        $this->server_socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($this->server_socket === false) {
            $this->abort("Не удалось создать серверный сокет !");
        }
        // Привязка серверного сокета к хосту и порту
        if (socket_bind($this->server_socket, $this->host, $this->port) === false) {
                $this->abort("Не удалось привязать имя хоста и порт к серверному сокету !");
        }
        $this->write_log("Сервер запущен ...");
        // Обработка сигнала и нажатия ctrl+C для выключения сервера
        pcntl_signal(SIGINT, function($signo) {
            $this->close();
            exit(0);
        });
        // Обработка сигнала, когда дочерний процесс закончил чтение сообщений от клиента
        pcntl_signal(SIGTERM, function($signo) {
            $this->proccess_messages();
        });
        // Выделение разделяемой памяти для хранения сообщений
        $this->shm_key = ftok(__FILE__, 't');
        $shmid = shmop_open($this->shm_key, "c", 0666, self::SHM_SIZE);
        shmop_close($shmid);
        // Ожидание подключения клиентов
        $this->waiting();
    }

    // Закрытие сервера
    private function close() {
        $this->write_log("Закрытие соединений ...");
        // Закрытие дочерних процессов
        foreach ($this->clients as $client) {
            $this->remove_client($client["socket"]);
        }
        // Удаление разделяемой памяти
        $shmid = shmop_open($this->shm_key, "c", 0666, self::SHM_SIZE);
        shmop_delete($shmid);
        shmop_close($shmid);
        $this->write_log("Завершение работы сервера ...");
    }

    // Ожидание подключений клиентов
    private function waiting() {
        // Подготовка серверного сокета к прослушиванию клиентов
        if (socket_listen($this->server_socket, self::CLIENT_MAX_COUNT) === false) {
            $this->abort("Не удалось начать прослушивание входящих соединений !");
        }
        socket_set_nonblock($this->server_socket);
        $this->write_log("Сервер готов к установке соединений ...");
        $is_client_max_count = false;
        // Ожидание подключений клиентов
        while (true) {
            // Обработка сигналов
            pcntl_signal_dispatch();
            // Если достигнуто максимальное число клиентов
            if ($this->client_count === self::CLIENT_MAX_COUNT) {
                if (!$is_client_max_count) {
                    $this->write_log("Достигнуто максимальное число клиентов !");
                }
                $is_client_max_count = true;
            } else {
                $is_client_max_count = false;
                // Принятие соединения
                $client_socket = socket_accept($this->server_socket);
                if ($client_socket === false) {
                    continue;
                }
                $this->write_log("? Соединение с новым клиентом");
                // Создание отдельного процесса для прослушивания клиента
                $pid = pcntl_fork();
                if ($pid === 0) {
                    $this->listen($client_socket);
                } else {
                    // Добавление клиента в родительском процессе
                    $this->append_client($client_socket, $pid);
                }
            }
        }
    }

    // Прослушивание и отправка сообщений клиенту
    private function listen($client_socket) {
        // Обработка сигнала SIGINT
        pcntl_signal(SIGINT, function($signo) use ($client_socket) {
            exit(0);
        });
        // Прослушивание клиента
        socket_set_nonblock($client_socket);
        $client_login = "";
        while (true) {
            $this->read($client_socket);
            pcntl_signal_dispatch();
        }
    }

    // Отправка сообщения клиенту
    private function write($client_socket, string $message) {
        $r = @socket_write($client_socket, $message, self::MESSAGE_MAX_LENGTH);
        // Если клиент разорвал соединение
        $sle = socket_last_error($client_socket);
        if ($sle !== 0 and $sle !== 11) {
            $this->remove_client($client_socket);
        }
    }

    // Отправка сообщения всем клиентам
    private function write_all(string $message) {
        foreach ($this->clients as $client) {
            $this->write($client["socket"], $message);
        }
    }

    // Логирование на сервере
    private function write_log(string $text) {
        print($text."\n");
    }

    // Добавление информации о клиенте
    private function append_client($client_socket,
                                   int $pid) {
        $this->clients[] = [
            "login"  => "",
            "socket" => $client_socket,
            "pid"    => $pid
        ];
        ++$this->client_count;
    }

    // Удаление информации о клиенте
    private function remove_client($client_socket) {
        $login = null;
        $this->clients = array_filter($this->clients, function ($client) use ($client_socket, &$login) {
            if ($client["socket"] === $client_socket) {
                if (!empty($client["login"])) {
                    $this->write_log("- $client[login] отсоединился");
                }
                posix_kill($client["pid"], SIGINT);
                socket_close($client["socket"]);
                --$this->client_count;
                $login = $client["login"];
                return false;
            }
            return true;
        });
        $this->write_all(self::message("- $login отсоединился"));
    }

    // Добавление логина клиента по его сокету
    private function append_client_login($client_socket, string $login) {
        foreach ($this->clients as &$client) {
            if ($client["socket"] === $client_socket) {
                $client["login"] = $login;
            }
        }
    }

    // Получение сообщения от клиента
    private function read($client_socket) {
        $messages = @socket_read($client_socket, self::MESSAGE_MAX_LENGTH);
        // Если клиент разорвал соединение
        $sle = socket_last_error($client_socket);
        if ($sle !== 0 and $sle !== 11) {
            exit(0);
        }
        if (!empty($messages)) {
            // Запись сообщений в разделяемую память
            $shmid = shmop_open($this->shm_key, "c", 0666, self::SHM_SIZE);
            $data = trim(shmop_read($shmid, 0, self::SHM_SIZE));
            $data .= posix_getpid() . $messages;
            shmop_write($shmid, $data, 0);
            shmop_close($shmid);
            posix_kill(posix_getppid(), SIGTERM);
        }
    }

    // Обработка поступающих в разделяемую память сообщений
    private function proccess_messages() {
        // Буфер сообщений
        static $buf = "";
        static $pid = null;
        $message = "";
        // Если буфер сообщений пуст, считать следующую порцию
        if (empty($buf)) {
            $shmid = shmop_open($this->shm_key, "c", 0666, self::SHM_SIZE);
            $buf = trim(shmop_read($shmid, 0, self::SHM_SIZE));
            $num = (int)$buf;
            $buf = substr($buf, strlen("$num"));
            shmop_write($shmid, str_repeat("\0", self::SHM_SIZE), 0);
            if (is_integer($num)) {
                $pid = $num;
            }
            shmop_close($shmid);
        }
        if (!empty($buf)) {
            // Если от клиента поступили данные
            $messages = array_slice(explode(self::DATA_END, $buf), 0, -1);
            $messages = array_map(function ($message) {
                return $message . self::DATA_END;
            }, $messages);
            $buf = implode("", array_slice($messages, 1));
            $message = $messages[0];
        }
        if (!empty($message)) {
            list($client_socket, $client_login) = $this->get_socket_and_login_by_pid($pid);
            $this->proccess_message($client_socket, $message, $client_login);
        }
    }

    // Получение сокета и логина клиента по pid процесса-слушателя
    private function get_socket_and_login_by_pid(int $pid) {
        foreach ($this->clients as $client) {
            if ($client["pid"] === $pid) {
                return [$client["socket"], $client["login"]];
            }
        }
    }

    // Обработка сообщения от клиента и получение его логина
    private function proccess_message($client_socket,
                                      string $client_message,
                                      string $client_login) : string {
        list($message_name, $message_data) = self::parse($client_message);
        switch ($message_name) {
            case "LOGIN":   $client_login = $this->proccess_message_login($client_socket, $message_data);
                            break;
            case "MESSAGE": $this->proccess_message_message($client_socket, $client_login, $message_data);
                            break;
            case "QUIT":    $this->proccess_message_quit($client_socket);
                            break;
            default:        $this->abort("Неизвестное сообщение от клиента: '$client_message' !");
        }
        return $client_login;
    }

    // Обработка входящего от клиента сообщения авторизации
    private function proccess_message_login($client_socket, string $login) : string {
        $login_pattern = '/^[\w_]+$/';
        $message = "";
        $result_login = "";
        $ok_login = false;
        if (preg_match($login_pattern, $login)) {
            if ($this->is_login_exists($login)) {
                $message = self::login("EXISTS");
                $this->write_log("? Новый клиент попытался соединиться с уже занятым логином");
            } else {
                $ok_login = true;
                $message = self::login("OK");
                $this->append_client_login($client_socket, $login);
                $this->write_log("+ $login соединился");
                $result_login = $login;
            }
        } else {
            $message = self::login("BAD");
            $this->write_log("? Новый клиент попытался соединиться с неправильным логином");
        }
        $this->write($client_socket, $message);
        if ($ok_login) {
            $this->write_all(self::message("+ $login соединился"));
        }
        return $result_login;
    }

    // Обработка входящего от клиента сообщения с текстом
    private function proccess_message_message($client_socket,
                                              string $client_login,
                                              string $text) {
        $this->write_all(self::message("$client_login: $text"));
        $this->write_log("$client_login: $text");
    }

    // Обработка входящего от клиента сообщения о завершении работы
    private function proccess_message_quit($client_socket) {
        $this->remove_client($client_socket);
    }

    // Проверка существования клиента с заданым логином
    private function is_login_exists(string $login) : bool {
        foreach ($this->clients as $client) {
            if ($client["login"] === $login) {
                return true;
            }
        }
        return false;
    }

    // Аварийное закрытие сервера и вывод сообщения
    private function abort(string $text) {
        $this->write_log($text);
        $this->close();
    }

    // Ответ клиенту на сообщение об авторизации
    protected static function login(string $data) : string {
        return "LOGIN" . self::DATA_SEP . $data . self::DATA_END;
    }

}
