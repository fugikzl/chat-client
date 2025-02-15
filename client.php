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
    $redraw = function () use ($messages, &$inputLine, &$userlogin): void {
        echo "\033[H\033[J";

        foreach ($messages->getQueue() as $msg) {
            echo $msg . PHP_EOL;
        }

        $userlogin = $userlogin ?? 'you';

        echo "$userlogin: " . $inputLine . "\033[K";
    };

    $remote = readline('pls enter remote address: ');
    $chat = new AsyncTcpConnection('ws://' . $remote . ':' . PORT, [
        'ssl' => [
            'verify' => false
        ]
    ]);
    $chat->websocketPingInterval = 5;

    $chat->onConnect = function (TcpConnection $tcpConnection): void {
        _log('Connection with remote chat established');
    };

    $chat->onMessage = function (TcpConnection $tcpConnection, string $data) use (&$messages, $redraw, &$userlogin): void {
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
                $messages->push($author . ': ' . $msg->data);
                $redraw();
                break;
            case Action::History:
                foreach (json_decode($msg->data, true) as $mes) {
                    $messages->push($mes);
                };
                $redraw();
                stream_set_blocking(STDIN, false);
                Timer::add(0.1, function () use ($tcpConnection): void {
                    $input = fgets(STDIN);
                    if ($input !== false) {
                        $input = trim($input);
                        if (!empty($input)) {
                            echo "\033[H";
                            $tcpConnection->send(strval(new Msg(Action::Message, $input)));
                        }
                    }
                });
                break;
            default:
                _log('can not determine msg: ' . $msg->__tostring());
                break;
        }
    };

    $chat->onClose = function (): void {
        _log('Connection closed');
        stream_set_blocking(STDIN, true);
        Worker::stopAll();
    };

    $chat->connect();
};


Worker::runAll();
