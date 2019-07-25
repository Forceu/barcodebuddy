<?php
/**
 * Barcode Buddy for Grocy
 *
 * PHP version 7
 *
 * LICENSE: This source file is subject to version 3.0 of the GNU General
 * Public License v3.0 that is attached to this project.
 *
 * @author     Marc Ole Bulling
 * @copyright  2019 Marc Ole Bulling
 * @license    https://www.gnu.org/licenses/gpl-3.0.en.html  GNU GPL v3.0
 * @since      File available since Release 1.0
 */


/**
 * This file starts the websocket server and needs to be called from the command line
 * to work
 *
 * @author     Marc Ole Bulling
 * @copyright  2019 Marc Ole Bulling
 * @license    https://www.gnu.org/licenses/gpl-3.0.en.html  GNU GPL v3.0
 * @since      File available since Release 1.0
 */



require __DIR__ . '/incl/websocket/php-websocket/src/Connection.php';
require __DIR__ . '/incl/websocket/php-websocket/src/Socket.php';
require __DIR__ . '/incl/websocket/php-websocket/src/Server.php';

require __DIR__ . '/incl/websocket/php-websocket/src/Application/ApplicationInterface.php';
require __DIR__ . '/incl/websocket/php-websocket/src/Application/Application.php';
require __DIR__ . '/incl/websocket/ScreenApplication.php';


require_once __DIR__ . '/config.php';


//Uncomment this to be able to access statistics
//require __DIR__ . '/php-websocket/src/Application/StatusApplication.php';




$server = new \Bloatless\WebSocket\Server('127.0.0.1', WEBSOCKET_SERVER_PORT);

// server settings:
$server->setMaxClients(100);
$server->setCheckOrigin(false);
$server->setMaxConnectionsPerIp(100);
$server->setMaxRequestsPerMinute(2000);


//Uncomment this to be able to access statistics
//$server->registerApplication('status', \Bloatless\WebSocket\Application\StatusApplication::getInstance());
$server->registerApplication('screen', \Bloatless\WebSocket\Application\ScreenApplication::getInstance());

$server->run();
