<p align="center">
    <img src="https://bloatless.org/img/logo.svg" width="60px" height="80px">
</p>

<h1 align="center">Bloatless PHP WebSockets</h1>

<p align="center">
    Simple WebSocket server and client implemented in PHP.
</p>

## About

This application is an extremely simple implementation of the [WebSocket Protocol](https://tools.ietf.org/html/rfc6455)
in PHP. It includes a server as well as a client. This implementation is optimal to get started with WebSockets and
learn something. As soon as you want to create a full featured websocket based application you might want to switch
to more sophisticated solution.

## Installation

Clone or download the repository to your server.

### Requirements

* PHP >= 7.2 

Hint: You can use version 1.0 if you're still on PHP5.


## Usage

* Adjust `cli/server.php` to your requirements.
* Run: `php cli/server.php`

This will start a websocket server. (By default on localhost:8000)

### Server example

This will create a websocket server listening on port 8000.

There a two applications registred to the server. The demo application will be available at `ws://localhost:8000/demo`
and the status application will be available at `ws://localhost:8000/status`.

```php
// Require neccessary files here...

$server = new \Bloatless\WebSocket\Server('127.0.0.1', 8000);

// Server settings:
$server->setMaxClients(100);
$server->setCheckOrigin(false);
$server->setAllowedOrigin('foo.lh');
$server->setMaxConnectionsPerIp(100);
$server->setMaxRequestsPerMinute(2000);

// Add your applications here:
$server->registerApplication('status', \Bloatless\WebSocket\Application\StatusApplication::getInstance());
$server->registerApplication('demo', \Bloatless\WebSocket\Application\DemoApplication::getInstance());

$server->run();

```

### Client example

This creates a WebSocket cliente, connects to a server and sends a message to the server:

```php
$client = new \Bloatless\WebSocket\Client;
$client->connect('127.0.0.1', 8000, '/demo', 'foo.lh');
$client->sendData([
    'action' => 'echo',
    'data' => 'Hello Wolrd!'
]);
```

### Browser example

The repository contains two demo-pages to call in your browser. You can find them in the `public` folder.
The `index.html` is a simple application which you can use to send messages to the server.

The `status.html` will display various server information.