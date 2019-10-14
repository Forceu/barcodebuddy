<?php

namespace WebSocket\Application;

class ScreenApplication extends Application {

    private $currentBBMode = "Consume";
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
    
    private function _actionEcho($text) {
        $encodedData = $this->_encodeData('echo', $text);
        foreach ($this->_clients as $sendto) {
            $sendto->send($encodedData);
        }
    }

 private function _actionGetmode(string $text): void
    {
        foreach ($this->_clients as $sendto) {
            $sendto->send('{"action":"getmode","data":"4'.$this->currentBBMode.'"}');
        }
    }

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
