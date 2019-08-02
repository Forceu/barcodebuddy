<?php

namespace WebSocket;

/**
 * Socket class
 *
 * @author Moritz Wutz <moritzwutz@gmail.com>
 * @author Nico Kaiser <nico@kaiser.me>
 * @version 0.2
 */

/**
 * This is the main socket class
 */
class Socket
{
    /**
     * @var Socket Holds the master socket
     */
    protected $master;

    /**
     * @var array Holds all connected sockets
     */
    protected $allsockets = array();
	protected $context = null;
	protected $ssl = false;

	public function __construct($host = 'localhost', $port = 8000, $ssl = false)
    {
        ob_implicit_flush(true);
		$this->ssl = $ssl;
        $this->createSocket($host, $port);
    }

    /**
     * Create a socket on given host/port
     * 
     * @param string $host The host/bind address to use
     * @param int $port The actual port to bind on
     */
	private function createSocket($host, $port)
	{
		$protocol = ($this->ssl === true) ? 'tls://' : 'tcp://';
		$url = $protocol.$host.':'.$port;
		$this->context = stream_context_create();
		if($this->ssl === true)
		{
			$this->applySSLContext();
		}
		if(!$this->master = stream_socket_server($url, $errno, $err, STREAM_SERVER_BIND|STREAM_SERVER_LISTEN, $this->context))
		{
			die('Error creating socket: ' . $err);
		}		
		
		$this->allsockets[] = $this->master;
	}  
	
	private function applySSLContext()
	{		
		$pem_file = './server.pem';
		$pem_passphrase = 'shinywss';		
		
		// Generate PEM file
		if(!file_exists($pem_file))
		{
			$dn = array(
				"countryName" => "DE",
				"stateOrProvinceName" => "none",
				"localityName" => "none",
				"organizationName" => "none",
				"organizationalUnitName" => "none",
				"commonName" => "foo.lh",
				"emailAddress" => "baz@foo.lh"
			);
			$privkey = openssl_pkey_new();
			$cert    = openssl_csr_new($dn, $privkey);
			$cert    = openssl_csr_sign($cert, null, $privkey, 365);			
			$pem = array();
			openssl_x509_export($cert, $pem[0]);
			openssl_pkey_export($privkey, $pem[1], $pem_passphrase);
			$pem = implode($pem);		
			file_put_contents($pem_file, $pem);
		}
		
		// apply ssl context:
		stream_context_set_option($this->context, 'ssl', 'local_cert', $pem_file);
		stream_context_set_option($this->context, 'ssl', 'passphrase', $pem_passphrase);
		stream_context_set_option($this->context, 'ssl', 'allow_self_signed', true);
		stream_context_set_option($this->context, 'ssl', 'verify_peer', false);		
	}
	
	// method originally found in phpws project:
	protected function readBuffer($resource)
	{
		if($this->ssl === true)
		{
			$buffer = fread($resource, 8192);
			// extremely strange chrome behavior: first frame with ssl only contains 1 byte?!
			if(strlen($buffer) === 1)
			{
				$buffer .= fread($resource, 8192);
			}		
			return $buffer;
		}
		else
		{
			$buffer = '';
			$buffsize = 8192;
			$metadata['unread_bytes'] = 0;	
			do
			{
				if(feof($resource))
				{
					return false;
				}
				$result = fread($resource, $buffsize);				
				if($result === false || feof($resource))
				{
					return false;
				}				
				$buffer .= $result;				
				$metadata = stream_get_meta_data($resource);			
				$buffsize = ($metadata['unread_bytes'] > $buffsize) ? $buffsize : $metadata['unread_bytes'];
			} while($metadata['unread_bytes'] > 0);		
			
			return $buffer;
		}
	}
	
	// method originally found in phpws project:
	public function writeBuffer($resource, $string)
	{		
		$stringLength = strlen($string);
		for($written = 0; $written < $stringLength; $written += $fwrite)
		{
			$fwrite = @fwrite($resource, substr($string, $written));			
			if($fwrite === false)
			{
				return false;
			}
			elseif($fwrite === 0)
			{
				return false;
			}
		}		
		return $written;
	}
}