<?php

require_once "chat/chatclient.php";



// Создание клиента
$client = new Chat\ChatClient();

// Регистрация
print("Зарегистрироваться (y/n) ?\n");
$answer = strtolower(trim(fgets(STDIN)));
if ($answer === "y") {
    print("### Регистрация ###\n");
    do {
        print("Введите логин: ");
        $login = trim(fgets(STDIN));
        print("Введите пароль: ");
        $pwd = trim(fgets(STDIN));
        $status = $client->registration($login, $pwd);
        if ($status === false) {
            $client->close();
            exit(0);
        }
        switch ($status) {
            case "OK":
                print("Регистрация успешна !\n");
                break;
            case "EXISTS_LOGIN":
                print("Указанный логин уже занят !\n");
                break;
            case "BAD_LOGIN":
                print("Указан некоректный логин !\n");
                break;
            case "BAD_PWD":
                print("Указан некоректный пароль !\n");
                break;
        }
    } while ($status !== "OK");
}

// Авторизация
print("### Авторизация ###\n");
do {
    print("Введите логин: ");
    $login = trim(fgets(STDIN));
    print("Введите пароль: ");
    $pwd = trim(fgets(STDIN));
    $status = $client->authorization($login, $pwd);
    if ($status === false) {
        $client->close();
        exit(0);
    }
    switch ($status) {
        case "OK":
            print("Авторизация успешна !\n");
            break;
        case "ALREADY":
            print("Пользователь с таким логином уже авторизован !\n");
            break;
        case "BAD":
            print("Указан неправильный логин или пароль !\n");
            break;
    }
} while ($status !== "OK");

// Создание двух процессов: для отправки и чтения сообщений
$pid = pcntl_fork();

// Обработка ctrl+C
pcntl_signal(SIGINT, function ($signo) {
    exit(0);
});

if ($pid === 0) {
    read($client);
} else {
    write($client);
}



// Чтение сообщений с сервера
function read($client) {
    while (true) {
        pcntl_signal_dispatch();
        $text = $client->read_text();
        if ($text === false) {
            $client->close();
            posix_kill(posix_getpid(), SIGINT);
            posix_kill(posix_getppid(), SIGINT);
        }
        if (!empty($text)) {
            print($text."\n");
        }
    }
}


// Запись сообщений на сервер
function write($client) {
    while (true) {
        pcntl_signal_dispatch();
        $text = trim(fgets(STDIN));
        if (!empty($text)) {
            $client->write_text($text);
        }
    }
}
