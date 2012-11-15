<?php
/*
 * CVM is more free software. It is licensed under the WTFPL, which
 * allows you to do pretty much anything with it, without having to
 * ask permission. Commercial use is allowed, and no attribution is
 * required. We do politely request that you share your modifications
 * to benefit other developers, but you are under no enforced
 * obligation to do so :)
 * 
 * Please read the accompanying LICENSE document for the full WTFPL
 * licensing text.
 */
 
if(!isset($_CVM)) { die("Unauthorized."); }

class SshConnector extends CPHPBaseClass
{
	public $connected = false;
	public $authenticated = false;
	public $connection = null;
	
	public $host = "localhost";
	public $port = 22;
	public $user = "root";
	public $key = "";
	public $pubkey = "";
	public $keytype = "ssh-rsa";
	
	public $helper = "~/runhelper";
	
	private $cache = "";
	
	public function RunCommand($command, $throw_exception = false)
	{
		try
		{
			if($this->connected == false && $this->authenticated == false)
			{
				$this->Connect();
			}
			
			return $this->DoCommand($command, $throw_exception);
		}
		catch (SshConnectException $e)
		{
			$error = $e->getMessage();
			throw new SshConnectException("Could not run command {$command}: Failed to connect: {$error}");
		}
	}
	
	public function RunCommandCached($command, $throw_exception = false)
	{
		if(!isset($this->cache[serialize($command)]))
		{
			$result = $this->RunCommand($command, $throw_exception);
			$this->cache[serialize($command)] = $result;
			return $result;
		}
		else
		{
			return $this->cache[serialize($command)];
		}
	}
	
	public function Connect()
	{
		$options = array(
			'hostkey' => $this->keytype
		);
		
		if($this->connection = @ssh2_connect($this->host, $this->port, $options))
		{
			$this->connected = true;
			
			if(empty($this->passphrase))
			{
				$result = ssh2_auth_pubkey_file($this->connection, $this->user, $this->pubkey, $this->key);
			}
			else
			{
				$result = ssh2_auth_pubkey_file($this->connection, $this->user, $this->pubkey, $this->key, $this->passphrase);
			}
			
			if($result === true)
			{
				$this->authenticated = true;
				return true;
			}
			else
			{
				throw new SshAuthException("Could not connect to {$this->host}:{$this->port}: Key authentication failed");
			}
		}
		else
		{
			throw new SshConnectException("Could not connect to {$this->host}:{$this->port}: {$error}");
		}
		
		return false;
	}
	
	private function DoCommand($command, $throw_exception)
	{
		$command = base64_encode(json_encode($command));
		$command = "{$this->helper} {$command}";
		
		$stream = ssh2_exec($this->connection, $command);
		$error_stream = ssh2_fetch_stream($stream, SSH2_STREAM_STDERR);
		
		stream_set_blocking($stream, true);
		stream_set_blocking($error_stream, true);
		
		$result = stream_get_contents($stream);
		$error = stream_get_contents($error_stream);
		
		if(strpos($error, "No such file or directory") !== false)
		{
			throw new Exception("The runhelper is not installed on the node or an error occurred.");
		}
		
		$returndata = json_decode($result);
		
		$returndata->stderr = trim($returndata->stderr);
		
		fclose($stream);
		fclose($error_stream);
		
		if($returndata->returncode != 0 && $throw_exception === true)
		{
			throw new SshExitException("Non-zero exit code returned: {$returndata->stderr}", $returndata->returncode);
		}
		
		return $returndata;
	}
}
