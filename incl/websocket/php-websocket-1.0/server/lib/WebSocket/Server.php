<?php
namespace WebSocket;

/**
 * Shiny WSS
 *
 * @author Nico Kaiser <nico@kaiser.me>
 * @author Simon Samtleben <web@lemmingzshadow.net>
 */
class Server extends Socket
{   
    protected $clients = array();
    protected $applications = array();
	private $_ipStorage = array();
	private $_requestStorage = array();
	
	// server settings:
	private $_checkOrigin = true;
	private $_allowedOrigins = array();
	private $_maxClients = 30;
	private $_maxConnectionsPerIp = 5;
	private $_maxRequestsPerMinute = 50;

    public function __construct($host = 'localhost', $port = 8000, $ssl = false)
    {
        parent::__construct($host, $port, $ssl);
        $this->log('Server created');
    }

    /**
     * Creates a connection from a socket resource
     *
     * @param resource $resource A socket resource
     * @return Connection
     */
    protected function createConnection($resource)
    {
        return new Connection($this, $resource);
    }

	/**
	 * Main server method. Listens for connections, handles connectes/disconnectes, e.g.
	 */
	public function run()
	{
		while(true)
		{
			$changed_sockets = $this->allsockets;
			@stream_select($changed_sockets, $write = null, $except = null, 0, 5000);			
			foreach($changed_sockets as $socket)
			{
				if($socket == $this->master)
				{
					if(($ressource = stream_socket_accept($this->master)) === false)
					{
						$this->log('Socket error: ' . socket_strerror(socket_last_error($ressource)));
						continue;
					}
					else
					{
						$client = $this->createConnection($ressource);
						$this->clients[(int)$ressource] = $client;
						$this->allsockets[] = $ressource;
						
						if(count($this->clients) > $this->_maxClients)
						{
							$client->onDisconnect();
							if($this->getApplication('status') !== false)
							{
								$this->getApplication('status')->statusMsg('Attention: Client Limit Reached!', 'warning');
							}
							continue;
						}
						
						$this->_addIpToStorage($client->getClientIp());
						if($this->_checkMaxConnectionsPerIp($client->getClientIp()) === false)
						{
							$client->onDisconnect();
							if($this->getApplication('status') !== false)
							{
								$this->getApplication('status')->statusMsg('Connection/Ip limit for ip ' . $client->getClientIp() . ' was reached!', 'warning');
							}
							continue;
						}						
					}
				}
				else
				{					
					$client = $this->clients[(int)$socket];
					if(!is_object($client))
					{
						unset($this->clients[(int)$socket]);
						continue;
					}
					$data = $this->readBuffer($socket);					
					$bytes = strlen($data);
					
					if($bytes === 0)
					{
						$client->onDisconnect();						
						continue;
					}
					elseif($data === false)
					{
						$this->removeClientOnError($client);
						continue;
					}
					elseif($client->waitingForData === false && $this->_checkRequestLimit($client->getClientId()) === false)
					{
						$client->onDisconnect();
					}
					else
					{						
						$client->onData($data);
					}
				}
			}
		}
	}	

	/**
	 * Returns a server application.
	 * 
	 * @param string $key Name of application.
	 * @return object The application object. 
	 */
	public function getApplication($key)
	{
		if(empty($key))
		{
			return false;
		}
		if(array_key_exists($key, $this->applications))
		{
			return $this->applications[$key];
		}
		return false;
	}

	/**
	 * Adds a new application object to the application storage.
	 * 
	 * @param string $key Name of application.
	 * @param object $application The application object.
	 */
    public function registerApplication($key, $application)
    {
        $this->applications[$key] = $application;
		
		// status is kind of a system-app, needs some special cases:
		if($key === 'status')
		{
			$serverInfo = array(
				'maxClients' => $this->_maxClients,
				'maxConnectionsPerIp' => $this->_maxConnectionsPerIp,
				'maxRequetsPerMinute' => $this->_maxRequestsPerMinute,
			);
			$this->applications[$key]->setServerInfo($serverInfo);
		}
    }
    
	/**
	 * Echos a message to standard output.
	 * 
	 * @param string $message Message to display.
	 * @param string $type Type of message.
	 */
    public function log($message, $type = 'info')
    {
        echo date('Y-m-d H:i:s') . ' [' . ($type ? $type : 'error') . '] ' . $message . PHP_EOL;
    }
	
	/**
	 * Removes a client from client storage.
	 * 
	 * @param Object $client Client object.
	 */
	public function removeClientOnClose($client)
	{		
		$clientId = $client->getClientId();
		$clientIp = $client->getClientIp();
		$clientPort = $client->getClientPort();
		$resource = $client->getClientSocket();
		
		$this->_removeIpFromStorage($client->getClientIp());
		if(isset($this->_requestStorage[$clientId]))
		{
			unset($this->_requestStorage[$clientId]);
		}
		unset($this->clients[(int)$resource]);
		$index = array_search($resource, $this->allsockets);
		unset($this->allsockets[$index], $client);		
		
		// trigger status application:
		if($this->getApplication('status') !== false)
		{
			$this->getApplication('status')->clientDisconnected($clientIp, $clientPort);
		}
		unset($clientId, $clientIp, $clientPort, $resource);		
	}
	
	/**
	 * Removes a client and all references in case of timeout/error.
	 * @param object $client The client object to remove.
	 */
	public function removeClientOnError($client)
	{		// remove reference in clients app:
		if($client->getClientApplication() !== false)
		{
			$client->getClientApplication()->onDisconnect($client);	
		}
        
		$resource = $client->getClientSocket();
		$clientId = $client->getClientId();
		$clientIp = $client->getClientIp();
		$clientPort = $client->getClientPort();
		$this->_removeIpFromStorage($client->getClientIp());
		if(isset($this->_requestStorage[$clientId]))
		{
			unset($this->_requestStorage[$clientId]);
		}
		unset($this->clients[(int)$resource]);
		$index = array_search($resource, $this->allsockets);
		unset($this->allsockets[$index], $client);		
		
		// trigger status application:
		if($this->getApplication('status') !== false)
		{
			$this->getApplication('status')->clientDisconnected($clientIp, $clientPort);
		}
		unset($resource, $clientId, $clientIp, $clientPort);		
	}
	
	/**
	 * Checks if the submitted origin (part of websocket handshake) is allowed
	 * to connect. Allowed origins can be set at server startup.
	 * 
	 * @param string $domain The origin-domain from websocket handshake.
	 * @return bool If domain is allowed to connect method returns true. 
	 */
	public function checkOrigin($domain)
	{
		$domain = str_replace('http://', '', $domain);
		$domain = str_replace('https://', '', $domain);
		$domain = str_replace('www.', '', $domain);
		$domain = str_replace('/', '', $domain);
		
		return isset($this->_allowedOrigins[$domain]);
	}
	
	/**
	 * Adds a new ip to ip storage.
	 * 
	 * @param string $ip An ip address.
	 */
	private function _addIpToStorage($ip)
	{
		if(isset($this->_ipStorage[$ip]))
		{
			$this->_ipStorage[$ip]++;
		}
		else
		{
			$this->_ipStorage[$ip] = 1;
		}		
	}
	
	/**
	 * Removes an ip from ip storage.
	 * 
	 * @param string $ip An ip address.
	 * @return bool True if ip could be removed. 
	 */
	private function _removeIpFromStorage($ip)
	{
		if(!isset($this->_ipStorage[$ip]))
		{
			return false;
		}
		if($this->_ipStorage[$ip] === 1)
		{
			unset($this->_ipStorage[$ip]);
			return true;
		}
		$this->_ipStorage[$ip]--;
		
		return true;
	}
	
	/**
	 * Checks if an ip has reached the maximum connection limit.
	 * 
	 * @param string $ip An ip address.
	 * @return bool False if ip has reached max. connection limit. True if connection is allowed. 
	 */
	private function _checkMaxConnectionsPerIp($ip)
	{
		if(empty($ip))
		{
			return false;
		}
		if(!isset ($this->_ipStorage[$ip]))
		{
			return true;
		}
		return ($this->_ipStorage[$ip] > $this->_maxConnectionsPerIp) ? false : true;
	}
	
	/**
	 * Checkes if a client has reached its max. requests per minute limit.
	 * 
	 * @param string $clientId A client id. (unique client identifier)
	 * @return bool True if limit is not yet reached. False if request limit is reached. 
	 */
	private function _checkRequestLimit($clientId)
	{
		// no data in storage - no danger:
		if(!isset($this->_requestStorage[$clientId]))
		{
			$this->_requestStorage[$clientId] = array(
				'lastRequest' => time(),
				'totalRequests' => 1
			);
			return true;
		}
		
		// time since last request > 1min - no danger:
		if(time() - $this->_requestStorage[$clientId]['lastRequest'] > 60)
		{
			$this->_requestStorage[$clientId] = array(
				'lastRequest' => time(),
				'totalRequests' => 1
			);
			return true;
		}
		
		// did requests in last minute - check limits:
		if($this->_requestStorage[$clientId]['totalRequests'] > $this->_maxRequestsPerMinute)
		{
			return false;
		}
		
		$this->_requestStorage[$clientId]['totalRequests']++;
		return true;
	}
	
	/**
	 * Set whether the client origin should be checked on new connections.
	 * 
	 * @param bool $doOriginCheck
	 * @return bool True if value could validated and set successfully. 
	 */
	public function setCheckOrigin($doOriginCheck)
	{
		if(is_bool($doOriginCheck) === false)
		{
			return false;
		}
		$this->_checkOrigin = $doOriginCheck;
		return true;
	}
	
	/**
	 * Return value indicating if client origins are checked.
	 * @return bool True if origins are checked. 
	 */
	public function getCheckOrigin()
	{
		return $this->_checkOrigin;
	}

	/**
	 * Adds a domain to the allowed origin storage.
	 * 
	 * @param sting $domain A domain name from which connections to server are allowed.
	 * @return bool True if domain was added to storage.
	 */
	public function setAllowedOrigin($domain)
	{
		$domain = str_replace('http://', '', $domain);
		$domain = str_replace('www.', '', $domain);
		$domain = (strpos($domain, '/') !== false) ? substr($domain, 0, strpos($domain, '/')) : $domain;
		if(empty($domain))
		{
			return false;
		}
		$this->_allowedOrigins[$domain] = true;		
		return true;
	}
	
	/**
	 * Sets value for the max. connection per ip to this server.
	 * 
	 * @param int $limit Connection limit for an ip.
	 * @return bool True if value could be set. 
	 */
	public function setMaxConnectionsPerIp($limit)
	{
		if(!is_int($limit))
		{
			return false;
		}
		$this->_maxConnectionsPerIp = $limit;
		return true;
	}
	
	/**
	 * Returns the max. connections per ip value.
	 * 
	 * @return int Max. simoultanous  allowed connections for an ip to this server.
	 */
	public function getMaxConnectionsPerIp()
	{
		return $this->_maxConnectionsPerIp;
	}
	
	/**
	 * Sets how many requests a client is allowed to do per minute.
	 * 
	 * @param int $limit Requets/Min limit (per client).
	 * @return bool True if value could be set. 
	 */
	public function setMaxRequestsPerMinute($limit)
	{
		if(!is_int($limit))
		{
			return false;
		}
		$this->_maxRequestsPerMinute = $limit;
		return true;
	}
	
	/**
	 * Sets how many clients are allowed to connect to server until no more
	 * connections are accepted.
	 * 
	 * @param in $max Max. total connections to server.
	 * @return bool True if value could be set. 
	 */
	public function setMaxClients($max)
	{
		if((int)$max === 0)
		{
			return false;
		}
		$this->_maxClients = (int)$max;
		return true;
	}
	
	/**
	 * Returns total max. connection limit of server.
	 * 
	 * @return int Max. connections to this server. 
	 */
	public function getMaxClients()
	{
		return $this->_maxClients;
	}
}