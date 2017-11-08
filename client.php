<?php

require_once "chat/chatclient.php";



// Создание клиента
$client = new Chat\ChatClient();

// Авторизация
do {
    print("Введите логин: ");
    $login = trim(fgets(STDIN));
    $status = $client->authorization($login);
    if ($status === false) {
        $client->close();
        exit(0);
    }
    switch ($status) {
        case "OK":
            print("Авторизация успешна !\n");
            break;
        case "EXISTS":
            print("Указанный логин уже занят !\n");
            break;
        case "BAD":
            print("Указан неправильный логин !\n");
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
