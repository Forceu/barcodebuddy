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
 * Class for creating the websocket server. Used by PHP version lower than 7
 *
 * @author     Marc Ole Bulling
 * @copyright  2019 Marc Ole Bulling
 * @license    https://www.gnu.org/licenses/gpl-3.0.en.html  GNU GPL v3.0
 * @since      File available since Release 1.2
 *
 */


namespace WebSocket\Application;

class ScreenApplication extends Application {

    // current mode is stored
    private $currentBBMode = "Consume";
    //Only these modes are allowed as input
    private $allowedModes = array("Consume", "Consume (spoiled)", "Purchase", "Open", "Inventory", "Quantity", "Add to shoppinglist");

    private $_clients = array();
    
    public function onConnect($client) {
        $id                  = $client->getClientId();
        $this->_clients[$id] = $client;
    }
    
    public function onDisconnect($client) {
        $id = $client->getClientId();
        unset($this->_clients[$id]);
    }
    
    public function onData($data, $client) {
        $decodedData = $this->_decodeData($data);
        if ($decodedData === false) {
            // @todo: invalid request trigger error...
        }
        
        $actionName = '_action' . ucfirst($decodedData['action']);
        if (method_exists($this, $actionName)) {
            call_user_func(array(
                $this,
                $actionName
            ), $decodedData['data']);
        }
    }
    
    public function onBinaryData($data, $client) {
        $msg = 'Unsupported.';
    }
    


    /**
     * Echoes data back to client(s).
     *
     * @param string $text
     * @return void
     */
    private function _actionEcho($text) {
        $encodedData = $this->_encodeData('echo', $text);
        foreach ($this->_clients as $sendto) {
            $sendto->send($encodedData);
        }
    }

    /**
     * For getting the current mode
     *
     * @param string $text
     * @return void
     */
 private function _actionGetmode(string $text): void
    {
        foreach ($this->_clients as $sendto) {
            $sendto->send('{"action":"getmode","data":"4'.$this->currentBBMode.'"}');
        }
    }

    /**
     * For setting the current mode
     *
     * @param string $text
     * @return void
     */
    private function _actionSetmode(string $text): void
    {
        $encodedData = $this->encodeData('setmode', $text);
	if (in_array($text, $this->allowedModes)) {
		$this->currentBBMode=$text;
		foreach ($this->_clients as $sendto) {
		    $sendto->send('{"action":"setmode","data":"4'.$this->currentBBMode.'"}');
		}
	}
    }
}
