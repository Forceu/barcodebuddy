<?php

declare(strict_types=1);

namespace Bloatless\WebSocket;

use Bloatless\WebSocket\Application\ApplicationInterface;

class Connection
{
    public $waitingForData = false;

    /**
     * @var Server $server
     */
    private $server;

    /**
     * @var resource $socket
     */
    private $socket;

    /**
     * @var bool $handshaked
     */
    private $handshaked = false;


    /**
     * @var ApplicationInterface $application
     */
    private $application = null;

    /**
     * @var string $ip
     */
    private $ip;

    /**
     * @var int $port
     */
    private $port;

    /**
     * @var string $connectionId
     */
    private $connectionId = '';

    /**
     * @var string $dataBuffer
     */
    private $dataBuffer = '';

    /**
     * @param Server $server
     * @param resource $socket
     */
    public function __construct(Server $server, $socket)
    {
        $this->server = $server;
        $this->socket = $socket;

        // set some client-information:
        $socketName = stream_socket_get_name($socket, true);
        $tmp = explode(':', $socketName);
        $this->ip = $tmp[0];
        $this->port = (int) $tmp[1];
        $this->connectionId = md5($this->ip . $this->port . spl_object_hash($this));

        $this->log('Connected');
    }

    /**
     * Handles the client-server handshake.
     *
     * @param string $data
     * @throws \RuntimeException
     * @return bool
     */
    private function handshake(string $data): bool
    {
        $this->log('Performing handshake');
        $lines = preg_split("/\r\n/", $data);

        // check for valid http-header:
        if (!preg_match('/\AGET (\S+) HTTP\/1.1\z/', $lines[0], $matches)) {
            $this->log('Invalid request: ' . $lines[0]);
            $this->sendHttpResponse(400);
            stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR);
            return false;
        }

        // check for valid application:
        $path = $matches[1];
        $applicationKey = substr($path, 1);

        if ($this->server->hasApplication($applicationKey) === false) {
            $this->log('Invalid application: ' . $path);
            $this->sendHttpResponse(404);
            stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR);
            $this->server->removeClientOnError($this);
            return false;
        }

        $this->application = $this->server->getApplication($applicationKey);

        // generate headers array:
        $headers = [];
        foreach ($lines as $line) {
            $line = chop($line);
            if (preg_match('/\A(\S+): (.*)\z/', $line, $matches)) {
                $headers[$matches[1]] = $matches[2];
            }
        }

        // check for supported websocket version:
        if (!isset($headers['Sec-WebSocket-Version']) || $headers['Sec-WebSocket-Version'] < 6) {
            $this->log('Unsupported websocket version.');
            $this->sendHttpResponse(501);
            stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR);
            $this->server->removeClientOnError($this);
            return false;
        }

        // check origin:
        if ($this->server->getCheckOrigin() === true) {
            $origin = (isset($headers['Sec-WebSocket-Origin'])) ? $headers['Sec-WebSocket-Origin'] : '';
            $origin = (isset($headers['Origin'])) ? $headers['Origin'] : $origin;
            if (empty($origin)) {
                $this->log('No origin provided.');
                $this->sendHttpResponse(401);
                stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR);
                $this->server->removeClientOnError($this);
                return false;
            }

            if ($this->server->checkOrigin($origin) === false) {
                $this->log('Invalid origin provided.');
                $this->sendHttpResponse(401);
                stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR);
                $this->server->removeClientOnError($this);
                return false;
            }
        }

        // do handyshake: (hybi-10)
        $secKey = $headers['Sec-WebSocket-Key'];
        $secAccept = base64_encode(pack('H*', sha1($secKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
        $response = "HTTP/1.1 101 Switching Protocols\r\n";
        $response .= "Upgrade: websocket\r\n";
        $response .= "Connection: Upgrade\r\n";
        $response .= "Sec-WebSocket-Accept: " . $secAccept . "\r\n";
        if (isset($headers['Sec-WebSocket-Protocol']) && !empty($headers['Sec-WebSocket-Protocol'])) {
            $response .= "Sec-WebSocket-Protocol: " . substr($path, 1) . "\r\n";
        }
        $response .= "\r\n";
        try {
            $this->server->writeBuffer($this->socket, $response);
        } catch (\RuntimeException $e) {
            return false;
        }

        $this->handshaked = true;
        $this->log('Handshake sent');
        $this->application->onConnect($this);

        // trigger status application:
        if ($this->server->hasApplication('status')) {
            $this->server->getApplication('status')->clientConnected($this->ip, $this->port);
        }

        return true;
    }

    /**
     * Sends an http response to client.
     *
     * @param int $httpStatusCode
     * @throws \RuntimeException
     * @return void
     */
    public function sendHttpResponse(int $httpStatusCode = 400): void
    {
        $httpHeader = 'HTTP/1.1 ';
        switch ($httpStatusCode) {
            case 400:
                $httpHeader .= '400 Bad Request';
                break;
            case 401:
                $httpHeader .= '401 Unauthorized';
                break;
            case 403:
                $httpHeader .= '403 Forbidden';
                break;
            case 404:
                $httpHeader .= '404 Not Found';
                break;
            case 501:
                $httpHeader .= '501 Not Implemented';
                break;
        }
        $httpHeader .= "\r\n";
        try {
            $this->server->writeBuffer($this->socket, $httpHeader);
        } catch (\RuntimeException $e) {
            // @todo Handle write to socket error
        }
    }

    /**
     * Triggered whenever the server receives new data from a client.
     *
     * @param string $data
     * @return void
     */
    public function onData(string $data): void
    {
        if ($this->handshaked) {
            $this->handle($data);
        } else {
            $this->handshake($data);
        }
    }

    /**
     * Decodes incoming data and executes the requested action.
     *
     * @param string $data
     * @return bool
     */
    private function handle(string $data): bool
    {
        if ($this->waitingForData === true) {
            $data = $this->dataBuffer . $data;
            $this->dataBuffer = '';
            $this->waitingForData = false;
        }

        $decodedData = $this->hybi10Decode($data);

        if (empty($decodedData)) {
            $this->waitingForData = true;
            $this->dataBuffer .= $data;
            return false;
        } else {
            $this->dataBuffer = '';
            $this->waitingForData = false;
        }

        // trigger status application:
        if ($this->server->hasApplication('status')) {
            $this->server->getApplication('status')->clientActivity($this->port);
        }

        if (!isset($decodedData['type'])) {
            $this->sendHttpResponse(401);
            stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR);
            $this->server->removeClientOnError($this);
            return false;
        }

        switch ($decodedData['type']) {
            case 'text':
                $this->application->onData($decodedData['payload'], $this);
                break;
            case 'binary':
                $this->close(1003);
                break;
            case 'ping':
                $this->send($decodedData['payload'], 'pong', false);
                $this->log('Ping? Pong!');
                break;
            case 'pong':
                // server currently not sending pings, so no pong should be received.
                break;
            case 'close':
                $this->close();
                $this->log('Disconnected');
                break;
        }

        return true;
    }

    /**
     * Sends data to a client.
     *
     * @param string $payload
     * @param string $type
     * @param bool $masked
     * @return bool
     */
    public function send(string $payload, string $type = 'text', bool $masked = false): bool
    {

        try {
            $encodedData = $this->hybi10Encode($payload, $type, $masked);
            $this->server->writeBuffer($this->socket, $encodedData);
        } catch (\RuntimeException $e) {
            $this->server->removeClientOnError($this);
            return false;
        }

        return true;
    }

    /**
     * Closes connection to a client.
     *
     * @param int $statusCode
     * @return void
     */
    public function close(int $statusCode = 1000): void
    {
        $payload = str_split(sprintf('%016b', $statusCode), 8);
        $payload[0] = chr(bindec($payload[0]));
        $payload[1] = chr(bindec($payload[1]));
        $payload = implode('', $payload);

        switch ($statusCode) {
            case 1000:
                $payload .= 'normal closure';
                break;
            case 1001:
                $payload .= 'going away';
                break;
            case 1002:
                $payload .= 'protocol error';
                break;
            case 1003:
                $payload .= 'unknown data (opcode)';
                break;
            case 1004:
                $payload .= 'frame too large';
                break;
            case 1007:
                $payload .= 'utf8 expected';
                break;
            case 1008:
                $payload .= 'message violates server policy';
                break;
        }

        if ($this->send($payload, 'close', false) === false) {
            return;
        }

        if ($this->application) {
            $this->application->onDisconnect($this);
        }
        stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR);
        $this->server->removeClientOnClose($this);
    }


    /**
     * Triggered when a client closes the connection to server.
     *
     * @return void
     */
    public function onDisconnect(): void
    {
        $this->log('Disconnected', 'info');
        $this->close(1000);
    }

    /**
     * Writes a log message.
     *
     * @param string $message
     * @param string $type
     * @return void
     */
    public function log(string $message, string $type = 'info'): void
    {
        $this->server->log('[client ' . $this->ip . ':' . $this->port . '] ' . $message, $type);
    }

    /**
     * Encodes a frame/message according the the WebSocket protocol standard.
     *
     * @param string $payload
     * @param string $type
     * @param bool $masked
     * @throws \RuntimeException
     * @return string
     */
    private function hybi10Encode(string $payload, string $type = 'text', bool $masked = true): string
    {
        $frameHead = [];
        $payloadLength = strlen($payload);

        switch ($type) {
            case 'text':
                // first byte indicates FIN, Text-Frame (10000001):
                $frameHead[0] = 129;
                break;

            case 'close':
                // first byte indicates FIN, Close Frame(10001000):
                $frameHead[0] = 136;
                break;

            case 'ping':
                // first byte indicates FIN, Ping frame (10001001):
                $frameHead[0] = 137;
                break;

            case 'pong':
                // first byte indicates FIN, Pong frame (10001010):
                $frameHead[0] = 138;
                break;
        }

        // set mask and payload length (using 1, 3 or 9 bytes)
        if ($payloadLength > 65535) {
            $payloadLengthBin = str_split(sprintf('%064b', $payloadLength), 8);
            $frameHead[1] = ($masked === true) ? 255 : 127;
            for ($i = 0; $i < 8; $i++) {
                $frameHead[$i + 2] = bindec($payloadLengthBin[$i]);
            }
            // most significant bit MUST be 0 (close connection if frame too big)
            if ($frameHead[2] > 127) {
                $this->close(1004);
                throw new \RuntimeException('Invalid payload. Could not encode frame.');
            }
        } elseif ($payloadLength > 125) {
            $payloadLengthBin = str_split(sprintf('%016b', $payloadLength), 8);
            $frameHead[1] = ($masked === true) ? 254 : 126;
            $frameHead[2] = bindec($payloadLengthBin[0]);
            $frameHead[3] = bindec($payloadLengthBin[1]);
        } else {
            $frameHead[1] = ($masked === true) ? $payloadLength + 128 : $payloadLength;
        }

        // convert frame-head to string:
        foreach (array_keys($frameHead) as $i) {
            $frameHead[$i] = chr($frameHead[$i]);
        }
        if ($masked === true) {
            // generate a random mask:
            $mask = [];
            for ($i = 0; $i < 4; $i++) {
                $mask[$i] = chr(rand(0, 255));
            }

            $frameHead = array_merge($frameHead, $mask);
        }
        $frame = implode('', $frameHead);

        // append payload to frame:
        for ($i = 0; $i < $payloadLength; $i++) {
            $frame .= ($masked === true) ? $payload[$i] ^ $mask[$i % 4] : $payload[$i];
        }

        return $frame;
    }

    /**
     * Decodes a frame/message according to the WebSocket protocol standard.
     *
     * @param string $data
     * @return array
     */
    private function hybi10Decode(string $data): array
    {
        $unmaskedPayload = '';
        $decodedData = [];

        // estimate frame type:
        $firstByteBinary = sprintf('%08b', ord($data[0]));
        $secondByteBinary = sprintf('%08b', ord($data[1]));
        $opcode = bindec(substr($firstByteBinary, 4, 4));
        $isMasked = ($secondByteBinary[0] == '1') ? true : false;
        $payloadLength = ord($data[1]) & 127;

        // close connection if unmasked frame is received:
        if ($isMasked === false) {
            $this->close(1002);
        }

        switch ($opcode) {
            // text frame:
            case 1:
                $decodedData['type'] = 'text';
                break;
            case 2:
                $decodedData['type'] = 'binary';
                break;
            // connection close frame:
            case 8:
                $decodedData['type'] = 'close';
                break;
            // ping frame:
            case 9:
                $decodedData['type'] = 'ping';
                break;
            // pong frame:
            case 10:
                $decodedData['type'] = 'pong';
                break;
            default:
                // Close connection on unknown opcode:
                $this->close(1003);
                break;
        }

        if ($payloadLength === 126) {
            $mask = substr($data, 4, 4);
            $payloadOffset = 8;
            $dataLength = bindec(sprintf('%08b', ord($data[2])) . sprintf('%08b', ord($data[3]))) + $payloadOffset;
        } elseif ($payloadLength === 127) {
            $mask = substr($data, 10, 4);
            $payloadOffset = 14;
            $tmp = '';
            for ($i = 0; $i < 8; $i++) {
                $tmp .= sprintf('%08b', ord($data[$i + 2]));
            }
            $dataLength = bindec($tmp) + $payloadOffset;
            unset($tmp);
        } else {
            $mask = substr($data, 2, 4);
            $payloadOffset = 6;
            $dataLength = $payloadLength + $payloadOffset;
        }

        /**
         * We have to check for large frames here. socket_recv cuts at 1024 bytes
         * so if websocket-frame is > 1024 bytes we have to wait until whole
         * data is transferd.
         */
        if (strlen($data) < $dataLength) {
            return [];
        }

        if ($isMasked === true) {
            for ($i = $payloadOffset; $i < $dataLength; $i++) {
                $j = $i - $payloadOffset;
                if (isset($data[$i])) {
                    $unmaskedPayload .= $data[$i] ^ $mask[$j % 4];
                }
            }
            $decodedData['payload'] = $unmaskedPayload;
        } else {
            $payloadOffset = $payloadOffset - 4;
            $decodedData['payload'] = substr($data, $payloadOffset);
        }

        return $decodedData;
    }

    /**
     * Returns IP of the connected client.
     *
     * @return string
     */
    public function getClientIp(): string
    {
        return $this->ip;
    }

    /**
     * Returns the port the connection is handled on.
     *
     * @return int
     */
    public function getClientPort(): int
    {
        return $this->port;
    }

    /**
     * Returns the unique client id.
     *
     * @return string
     */
    public function getClientId(): string
    {
        return $this->connectionId;
    }

    /**
     * Retuns the socket/resource of the connection.
     *
     * @return resource
     */
    public function getClientSocket()
    {
        return $this->socket;
    }

    /**
     * Returns the application the client is connected to.
     *
     * @return ApplicationInterface|null
     */
    public function getClientApplication(): ?ApplicationInterface
    {
        return $this->application;
    }
}
