PHP WebSocket
=============
A websocket server implemented in php.

- Supports websocket draft hybi-10,13 (Currently tested with Chrome 18 and Firefox 11).
- Supports origin-check.
- Supports various security/performance settings.
- Supports binary frames. (Currently receive only)
- Supports wss. (Needs valid certificate in Firefox.)
- Application module, the server can be extended by custom behaviors.

## Bugs/Todos/Hints
- Add support for fragmented frames.

## Server example

This creates a server on localhost:8000 with one Application that listens on `ws://localhost:8000/demo`:

	$server = new \WebSocket\Server('127.0.0.1', 8000, false); // host,port,ssl

	// server settings:	
	$server->setCheckOrigin(true);
	$server->setAllowedOrigin('foo.lh');
	$server->setMaxClients(100);
	$server->setMaxConnectionsPerIp(20);
	$server->setMaxRequestsPerMinute(1000);

	$server->registerApplication('demo', \WebSocket\Application\DemoApplication::getInstance());
	$server->run();

## Libraries used

- [SplClassLoader](http://gist.github.com/221634) by the PHP Standards Working Group
- [jQuery](http://jquery.com/)
- [CoffeeScript PHP] (https://github.com/alxlit/coffeescript-php)

## Demo

- Check out http://jitt.li for a sample-project using this websocket server.