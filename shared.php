<?php

declare(strict_types=1);

const LOGIN_SEPARATOR = 'GagrGitler';
const PORT = 32799;

function _log(string $text): void
{
    echo $text . "\n";
}

enum Action: string
{
    case ReqLogin = 'ReqLogin';
    case Login = 'login';
    case Message = 'Message';
    case SucLogin = 'SucLogin';
    case History = 'History';
}

class FixedQueue
{
    private array $queue = [];
    private int $capacity;

    public function __construct(int $capacity)
    {
        $this->capacity = $capacity;
    }

    public function push(string $value): void
    {
        if (count($this->queue) >= $this->capacity) {
            array_shift($this->queue);
        }
        $this->queue[] = $value;
    }

    public function getQueue(): array
    {
        return $this->queue;
    }
}

class Msg
{
    public function __construct(
        public readonly Action $action,
        public readonly string $data,
        public readonly ?string $author = null
    ) {
    }

    public function __tostring(): string
    {
        return json_encode([
            'action' => $this->action->value,
            'data' => $this->data,
            'author' => $this->author,
        ]);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            Action::from($data['action']),
            $data['data'],
            $data['author']
        );
    }
}

function createLoginString(string $login, string $password): string
{
    return $login . LOGIN_SEPARATOR . $password;
}

/**
 * @param string $login
 * @param string $password
 * @return array{login: string, password: string}
 */
function decodeLoginString(string $loginString): array
{
    $exploded = explode(LOGIN_SEPARATOR, $loginString);

    return [
        'login' => $exploded[0],
        'password' => $exploded[1],
    ];
}
