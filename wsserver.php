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


require_once __DIR__ . '/incl/configProcessing.inc.php';
require_once __DIR__ . '/incl/websocketconnection.inc.php';
require_once __DIR__ . '/incl/internalChecking.inc.php';

const ENABLE_STATISTICS = false;

if (checkExtensionsInstalled()["result"] != RESULT_ALL_INSTALLED) {
    die("Not all required extensions are installed. Please run setup.php for more information.");
}


//If we are using php7.2+, the new server version is supported
if (isNewServerSupported()) {
    
    require __DIR__ . '/incl/websocket/php-websocket/src/Connection.php';
    require __DIR__ . '/incl/websocket/php-websocket/src/Socket.php';
    require __DIR__ . '/incl/websocket/php-websocket/src/Server.php';
    
    require __DIR__ . '/incl/websocket/php-websocket/src/Application/ApplicationInterface.php';
    require __DIR__ . '/incl/websocket/php-websocket/src/Application/Application.php';
    require __DIR__ . '/incl/websocket/ScreenApplication.php';
    if (ENABLE_STATISTICS) {
        require __DIR__ . '/php-websocket/src/Application/StatusApplication.php';
    }
    
    $server = new \Bloatless\WebSocket\Server('127.0.0.1', $CONFIG->PORT_WEBSOCKET_SERVER);
    
    // server settings:
    $server->setMaxClients(100);
    $server->setCheckOrigin(false);
    $server->setMaxConnectionsPerIp(100);
    $server->setMaxRequestsPerMinute(2000);
    
    $server->registerApplication('screen', \Bloatless\WebSocket\Application\ScreenApplication::getInstance());
    if (ENABLE_STATISTICS) {
        $server->registerApplication('status', \Bloatless\WebSocket\Application\StatusApplication::getInstance());
    }
    $server->run();
    
    
} else {
    //For older PHP versions, we are using websocket1.0
    
    
    echo "You are using PHP <7.2, starting old server";
    require(__DIR__ . '/incl/websocket/php-websocket-1.0/server/lib/SplClassLoader.php');
    
    $classLoader = new SplClassLoader('WebSocket', __DIR__ . '/incl/websocket/php-websocket-1.0/server/lib');
    $classLoader->register();
    
    $server = new \WebSocket\Server('127.0.0.1', $CONFIG->PORT_WEBSOCKET_SERVER, false);
    
    // server settings:
    $server->setMaxClients(100);
    $server->setCheckOrigin(false);
    $server->setMaxConnectionsPerIp(100);
    $server->setMaxRequestsPerMinute(2000);
    
    $server->registerApplication('screen', \WebSocket\Application\DemoApplication::getInstance());
    
    if (ENABLE_STATISTICS) {
        $server->registerApplication('status', \WebSocket\Application\StatusApplication::getInstance());
    }
    $server->run();
    
}

