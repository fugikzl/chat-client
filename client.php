<?php

declare(strict_types=1);

use Workerman\Connection\AsyncTcpConnection;
use Workerman\Connection\TcpConnection;
use Workerman\Timer;
use Workerman\Worker;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/shared.php';

$worker = new Worker();
$worker->onWorkerStart = function (): void {
    $userlogin = null;
    $messages = new FixedQueue(50);

    $inputLine = "";
    $messageBuffer = "";
    $blocking = false;

    $remote = readline('pls enter remote address: ');
    $chat = new AsyncTcpConnection('ws://' . $remote . ':' . PORT, [
        'ssl' => [
            'verify' => false
        ]
    ]);
    $chat->websocketPingInterval = 5;

    $chat->onConnect = function (TcpConnection $tcpConnection): void {
        _log('Connection with remote chat established');
        _log('[WAIT FOR LOGIN]');
    };

    $chat->onMessage = function (TcpConnection $tcpConnection, string $data) use (&$messages, &$userlogin, &$inputLine, &$messageBuffer, &$blocking): void {
        $msg = Msg::fromArray(json_decode($data, true));
        switch ($msg->action) {
            case Action::ReqLogin:
                $userlogin = readline('Please, input your login: ');
                $password = readline('Please, input your password: ');

                $tcpConnection->send(strval(new Msg(Action::Login, createLoginString(
                    $userlogin,
                    $password
                ))));
                break;
            case Action::SucLogin:
                break;

            case Action::Message:
                $author = $msg->author ?? 'anonymus' ;
                $messageBuffer = $messageBuffer . "\n". ($author . ': ' . $msg->data);
                break;
            case Action::History:
                foreach (json_decode($msg->data, true) as $mes) {
                    $messageBuffer = $messageBuffer . "\n" . $mes;
                };
                stream_set_blocking(STDIN, false);
                system("stty -icanon -echo");
                _log('[CHAT STARTED]');
                Timer::add(0.05, function () use ($tcpConnection, &$inputLine, &$messageBuffer, &$blocking): void {
                    $msgBuffArr = explode("\n", $messageBuffer);
                    if (count($msgBuffArr) > 1) {
                        $starting = 0;
                        foreach ($msgBuffArr as $index => $message) {
                            if ($message === '') {
                                if ($index === $starting) {
                                    $starting++;
                                }
                                continue;
                            }

                            if ($blocking) {
                                echo "\n";
                                $blocking = false;
                            }

                            if ($index === $starting) {
                                echo "\r" . str_repeat(' ', strlen($inputLine) + 5);
                                echo  "\r" . $message ;
                                continue;
                            }

                            echo  "\n" . $message ;
                        }
                        $messageBuffer = '';
                        echo "\n";
                        if (!empty($inputLine)) {
                            echo "You: " . $inputLine;
                        }
                        return;
                    }

                    $c = fread(STDIN, 10);
                    if ($c === false || $c === '') {
                        return;
                    }
                    if ($c[0] === "\n") {
                        $tcpConnection->send((new Msg(Action::Message, $inputLine))->__tostring());
                        $inputLine = "";
                        $blocking = true;
                        return;
                    }
                    $inputLine = $inputLine . $c;

                    $cc = strlen($inputLine);

                    $processedInput = "";
                    $backspaceCount = 0;

                    for ($i = strlen($inputLine) - 1; $i >= 0; $i--) {
                        if ($inputLine[$i] === chr(127)) {
                            $backspaceCount++;
                        } elseif ($backspaceCount > 0) {
                            $backspaceCount--;
                        } else {
                            $processedInput = $inputLine[$i] . $processedInput;
                        }
                    }

                    $inputLine = $processedInput;

                    if ($blocking) {
                        echo "\n";
                        $blocking = false;
                    }
                    echo "\r" . str_repeat(" ", $cc + 5 - $backspaceCount);
                    echo "\rYou: " . $inputLine;
                });
                break;
            default:
                _log('can not determine msg: ' . $msg->__tostring());
                break;
        }
    };

    $chat->onClose = function (): void {
        stream_set_blocking(STDIN, true);
        system("stty sane");
    };

    $chat->connect();
};


$worker->onWorkerStop = function (): void {
    stream_set_blocking(STDIN, true);
    system("stty sane");
};

$worker->onClose = function (): void {
    stream_set_blocking(STDIN, true);
    system("stty sane");
};

Worker::runAll();


(function (): void {
    stream_set_blocking(STDIN, true);
    system("stty sane");
})();
