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
 * Class for creating the websocket server. Used by PHP7+
 *
 * @author     Marc Ole Bulling
 * @copyright  2019 Marc Ole Bulling
 * @license    https://www.gnu.org/licenses/gpl-3.0.en.html  GNU GPL v3.0
 * @since      File available since Release 1.2
 *
 */
declare(strict_types=1);

namespace Bloatless\WebSocket\Application;

use Bloatless\WebSocket\Connection;

class ScreenApplication extends Application {

    // current mode is stored
    private $currentBBMode = "Consume";
    //Only these modes are allowed as input
    private $allowedModes = array("Consume", "Consume (spoiled)", "Purchase", "Open", "Inventory", "Quantity", "Add to shoppinglist");

    /**
     * @var array $clients
     */
    private $clients = [];

    /**
     * Handles new connections to the application.
     *
     * @param Connection $client
     * @return void
     */
    public function onConnect(Connection $client): void
    {
        $id = $client->getClientId();
        $this->clients[$id] = $client;
    }

    /**
     * Handles client disconnects.
     *
     * @param Connection $client
     * @return void
     */
    public function onDisconnect(Connection $client): void
    {
        $id = $client->getClientId();
        unset($this->clients[$id]);
    }

    /**
     * Handles incomming data/requests.
     * If valid action is given the according method will be called.
     *
     * @param string $data
     * @param Connection $client
     * @return void
     */
    public function onData(string $data, Connection $client): void
    {
        try {
            $decodedData = $this->decodeData($data);
            $actionName = 'action' . ucfirst($decodedData['action']);
            if (method_exists($this, $actionName)) {
                call_user_func([$this, $actionName], $decodedData['data']);
            }
        } catch (\RuntimeException $e) {
            // @todo Handle/Log error
        }
    }



    /**
     * Handles data pushed into the websocket server using the push-client.
     *
     * @param array $data
     */
    public function onIPCData(array $data): void
    {
        try {
            $decodedData = $this->decodeData($data);
            $actionName = 'action' . ucfirst($decodedData['action']);
            if (method_exists($this, $actionName)) {
                call_user_func([$this, $actionName], $decodedData['data']);
            }
        } catch (\RuntimeException $e) {
            // @todo Handle/Log error
        }
    }


    /**
     * Echoes data back to client(s).
     *
     * @param string $text
     * @return void
     */
    private function actionEcho(string $text): void
    {
        $encodedData = $this->encodeData('echo', $text);
        foreach ($this->clients as $sendto) {
            $sendto->send($encodedData);
        }
    }


    /**
     * For requesting the current mode
     *
     * @param string $text
     * @return void
     */
    private function actionGetmode(string $text): void
    {
        foreach ($this->clients as $sendto) {
            $sendto->send('{"action":"getmode","data":"4'.$this->currentBBMode.'"}');
        }
    }


    /**
     * For setting the current mode
     *
     * @param string $text
     * @return void
     */
    private function actionSetmode(string $text): void
    {
        $encodedData = $this->encodeData('setmode', $text);
	if (in_array($text, $this->allowedModes)) {
		$this->currentBBMode=$text;
		foreach ($this->clients as $sendto) {
		    $sendto->send('{"action":"setmode","data":"4'.$this->currentBBMode.'"}');
		}
	}
    }
}
