<?php
// ============================================
// KADILI NET - RouterOS API Client
// PHP 8.3 Compatible — Fixed strpos TypeError
// Updated: 2026
// ============================================

declare(strict_types=1);

namespace RouterOS;

class Config
{
    private array $params = [];

    public function __construct(array $params = [])
    {
        $this->params = array_merge([
            'host'           => '',
            'user'           => 'admin',
            'pass'           => '',
            'port'           => 8728,
            'ssl'            => false,
            'timeout'        => 10,
            'socket_timeout' => 30,
            'attempts'       => 3,
            'delay'          => 1,
        ], $params);
    }

    public function set(string $key, mixed $value): self
    {
        $this->params[$key] = $value;
        return $this;
    }

    public function get(string $key): mixed
    {
        return $this->params[$key] ?? null;
    }

    public function all(): array
    {
        return $this->params;
    }
}

// ============================================

class Query
{
    private string  $endpoint;
    private array   $attributes = [];
    private ?string $operations = null;
    private ?int    $tag        = null;

    public function __construct(mixed $endpoint, array $extra = [])
    {
        if (is_array($endpoint)) {
            $this->endpoint   = (string) array_shift($endpoint);
            $this->attributes = array_values($endpoint);
        } else {
            $this->endpoint = (string) $endpoint;
        }

        foreach ($extra as $attr) {
            $this->attributes[] = (string) $attr;
        }
    }

    public function where(string $key, mixed $operator = '=', mixed $value = null): self
    {
        if ($value === null) {
            $value    = $operator;
            $operator = '=';
        }
        $this->attributes[] = "?{$key}={$value}";
        return $this;
    }

    public function equal(string $key, mixed $value): self
    {
        $this->attributes[] = "={$key}={$value}";
        return $this;
    }

    public function operations(string $op): self
    {
        $this->operations = $op;
        return $this;
    }

    public function tag(int $tag): self
    {
        $this->tag = $tag;
        return $this;
    }

    public function add(string $attr): self
    {
        $this->attributes[] = $attr;
        return $this;
    }

    public function getEndpoint(): string    { return $this->endpoint; }
    public function getAttributes(): array   { return $this->attributes; }
    public function getOperations(): ?string { return $this->operations; }
    public function getTag(): ?int           { return $this->tag; }
}

// ============================================

class Client
{
    /** @var resource|null */
    private mixed  $socket = null;
    private Config $config;

    public function __construct(mixed $config)
    {
        if (is_array($config)) {
            $config = new Config($config);
        }
        $this->config = $config;
        $this->connect();
    }

    // ------------------------------------------
    // Connection
    // ------------------------------------------

    private function connect(): void
    {
        $host    = (string) ($this->config->get('host') ?? '');
        $port    = (int)    ($this->config->get('port') ?? 8728);
        $timeout = (int)    ($this->config->get('timeout') ?? 10);

        if ($host === '') {
            throw new \InvalidArgumentException('RouterOS host is not configured.');
        }

        $this->socket = @fsockopen($host, $port, $errno, $errstr, $timeout);

        if (!is_resource($this->socket)) {
            throw new \RuntimeException(
                "Cannot connect to RouterOS at {$host}:{$port} — {$errstr} (errno {$errno})"
            );
        }

        $socketTimeout = (int) ($this->config->get('socket_timeout') ?? 30);
        stream_set_timeout($this->socket, $socketTimeout);

        $this->login();
    }

    private function login(): void
    {
        $user = (string) ($this->config->get('user') ?? 'admin');
        $pass = (string) ($this->config->get('pass') ?? '');

        // Modern login (RouterOS 6.43+)
        $this->writeWord('/login');
        $this->writeWord("=name={$user}");
        $this->writeWord("=password={$pass}");
        $this->writeEndSentence();

        $response = $this->readResponse();

        // Legacy login (RouterOS < 6.43): MD5 challenge
        // SAFE: extractFirstWord() always returns string|null — never passes array to strpos
        $firstWord = $this->extractFirstWord($response);

        if ($firstWord !== null && str_contains($firstWord, '=ret=')) {
            $pos          = strpos($firstWord, '=ret=');
            $challengeHex = substr($firstWord, $pos + 5);
            $challenge    = pack('H*', $challengeHex);
            $md5          = md5(chr(0) . $pass . $challenge);

            $this->writeWord('/login');
            $this->writeWord("=name={$user}");
            $this->writeWord("=response=00{$md5}");
            $this->writeEndSentence();
            $this->readResponse();
        }

        if ($this->responseHasTrap($response)) {
            throw new \RuntimeException(
                'RouterOS login failed: ' . $this->extractTrapMessage($response)
            );
        }
    }

    // ------------------------------------------
    // Low-level socket I/O
    // ------------------------------------------

    private function writeWord(string $word): void
    {
        if (!is_resource($this->socket)) {
            throw new \RuntimeException('Socket is not open.');
        }

        $len = strlen($word);

        if ($len < 0x80) {
            fwrite($this->socket, chr($len));
        } elseif ($len < 0x4000) {
            $len |= 0x8000;
            fwrite($this->socket, chr(($len >> 8) & 0xFF) . chr($len & 0xFF));
        } elseif ($len < 0x200000) {
            $len |= 0xC00000;
            fwrite($this->socket,
                chr(($len >> 16) & 0xFF) .
                chr(($len >>  8) & 0xFF) .
                chr( $len        & 0xFF)
            );
        } else {
            fwrite($this->socket, chr(0xE0) .
                chr(($len >> 24) & 0xFF) .
                chr(($len >> 16) & 0xFF) .
                chr(($len >>  8) & 0xFF) .
                chr( $len        & 0xFF)
            );
        }

        if ($len > 0) {
            fwrite($this->socket, $word);
        }
    }

    private function writeEndSentence(): void
    {
        $this->writeWord('');
    }

    private function readWord(): string
    {
        if (!is_resource($this->socket)) {
            return '';
        }

        $raw = fread($this->socket, 1);
        if ($raw === false || $raw === '') {
            return '';
        }

        $byte = ord($raw);

        if (($byte & 0xE0) === 0xE0) {
            $len = (($byte & 0x0F) << 32)
                 | (ord(fread($this->socket, 1)) << 24)
                 | (ord(fread($this->socket, 1)) << 16)
                 | (ord(fread($this->socket, 1)) <<  8)
                 |  ord(fread($this->socket, 1));
        } elseif (($byte & 0xC0) === 0xC0) {
            $len = (($byte & 0x3F) << 16)
                 | (ord(fread($this->socket, 1)) << 8)
                 |  ord(fread($this->socket, 1));
        } elseif (($byte & 0x80) === 0x80) {
            $len = (($byte & 0x7F) << 8)
                 |  ord(fread($this->socket, 1));
        } else {
            $len = $byte;
        }

        if ($len === 0) {
            return '';
        }

        // Read exactly $len bytes in chunks
        $data      = '';
        $remaining = $len;
        while ($remaining > 0) {
            $chunk = fread($this->socket, $remaining);
            if ($chunk === false || $chunk === '') {
                break;
            }
            $data      .= $chunk;
            $remaining -= strlen($chunk);
        }

        return $data;
    }

    /**
     * Read a full response. Returns array of sentences (each sentence = array of strings).
     */
    private function readResponse(): array
    {
        $response = [];
        $sentence = [];

        while (is_resource($this->socket)) {
            $word = $this->readWord();

            if ($word === '') {
                // End-of-sentence marker
                if ($sentence !== []) {
                    $response[] = $sentence;
                }
                $sentence = [];

                // Stop on !done / !trap
                if ($response !== []) {
                    $last  = end($response);
                    $first = is_array($last) && isset($last[0]) && is_string($last[0])
                        ? $last[0]
                        : '';
                    if (str_starts_with($first, '!done') || str_starts_with($first, '!trap')) {
                        break;
                    }
                }
            } else {
                $sentence[] = $word;
            }
        }

        return $response;
    }

    // ------------------------------------------
    // Response helpers — all type-safe
    // ------------------------------------------

    /**
     * Returns the first non-empty string word in any sentence of $response.
     * NEVER calls strpos/str_contains on a non-string value.
     */
    private function extractFirstWord(array $response): ?string
    {
        foreach ($response as $sentence) {
            if (!is_array($sentence)) {
                continue;
            }
            foreach ($sentence as $word) {
                if (is_string($word) && $word !== '') {
                    return $word;
                }
            }
        }
        return null;
    }

    private function responseHasTrap(array $response): bool
    {
        foreach ($response as $sentence) {
            if (!is_array($sentence)) {
                continue;
            }
            $first = isset($sentence[0]) && is_string($sentence[0]) ? $sentence[0] : '';
            if (str_starts_with($first, '!trap')) {
                return true;
            }
        }
        return false;
    }

    private function extractTrapMessage(array $response): string
    {
        foreach ($response as $sentence) {
            if (!is_array($sentence)) {
                continue;
            }
            $first = isset($sentence[0]) && is_string($sentence[0]) ? $sentence[0] : '';
            if (!str_starts_with($first, '!trap')) {
                continue;
            }
            foreach ($sentence as $word) {
                if (is_string($word) && str_starts_with($word, '=message=')) {
                    return substr($word, 9);
                }
            }
        }
        return 'Unknown error';
    }

    // ------------------------------------------
    // Public query API
    // ------------------------------------------

    public function query(mixed $query, array $where = [], ?string $op = null, ?int $tag = null): self
    {
        if (is_string($query)) {
            $q = new Query($query);
            foreach ($where as $w) {
                if (is_array($w)) {
                    $q->where((string) ($w[0] ?? ''), $w[1] ?? '=', $w[2] ?? null);
                }
            }
            if ($op  !== null) { $q->operations($op); }
            if ($tag !== null) { $q->tag($tag); }
            $query = $q;
        }

        if (!($query instanceof Query)) {
            throw new \InvalidArgumentException('query() expects a string or Query instance.');
        }

        $this->writeWord($query->getEndpoint());

        foreach ($query->getAttributes() as $attr) {
            $this->writeWord((string) $attr);
        }

        if ($query->getOperations() !== null) {
            $this->writeWord($query->getOperations());
        }

        if ($query->getTag() !== null) {
            $this->writeWord('.tag=' . $query->getTag());
        }

        $this->writeEndSentence();
        return $this;
    }

    public function q(mixed $query, array $where = []): self
    {
        return $this->query($query, $where);
    }

    /**
     * Read response and return parsed key-value rows.
     */
    public function read(): array
    {
        $raw    = $this->readResponse();
        $result = [];

        foreach ($raw as $sentence) {
            if (!is_array($sentence) || $sentence === []) {
                continue;
            }

            $first = isset($sentence[0]) && is_string($sentence[0]) ? $sentence[0] : '';

            if ($first === '!done' || str_starts_with($first, '!trap')) {
                continue;
            }

            if ($first === '!re') {
                $row = [];
                foreach (array_slice($sentence, 1) as $word) {
                    if (!is_string($word)) {
                        continue;
                    }
                    if (str_starts_with($word, '=')) {
                        [$key, $val] = array_pad(explode('=', substr($word, 1), 2), 2, '');
                        $row[$key]   = $val;
                    }
                }
                $result[] = $row;
            }
        }

        return $result;
    }

    public function r(): array
    {
        return $this->read();
    }

    public function qr(mixed $query): array
    {
        return $this->query($query)->read();
    }

    // ------------------------------------------

    public function __destruct()
    {
        if (is_resource($this->socket)) {
            @fclose($this->socket);
        }
        $this->socket = null;
    }
}
