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

class Node extends CPHPDatabaseRecordClass
{
	public $table_name = "nodes";
	public $fill_query = "SELECT * FROM nodes WHERE `Id` = '%d'";
	public $verify_query = "SELECT * FROM nodes WHERE `Id` = '%d'";
	
	public $prototype = array(
		'string' => array(
			'Name'		=> "Name",
			'Hostname'	=> "Hostname",
			'PrivateKey'	=> "CustomPrivateKey",
			'PublicKey'	=> "CustomPublicKey",
			'User'		=> "User"
		),
		'numeric' => array(
			'Port'		=> "Port"
		),
		'boolean' => array(
			'HasCustomKey'	=> "HasCustomKey"
		)
	);
	
	public $ssh = null;
	
	protected function EventConstructed()
	{
		global $settings;
		
		$this->ssh = new SshConnector();
		
		$this->ssh->host = $this->sHostname;
		$this->ssh->port = $this->sPort;
		$this->ssh->user = $this->sUser;
		
		if($this->HasCustomKey === true)
		{
			$this->ssh->key = $this->sPrivateKey;
			$this->ssh->pubkey = $this->sPublicKey;
		}
		else
		{
			$this->ssh->key = $settings['master_privkey'];
			$this->ssh->pubkey = $settings['master_pubkey'];
		}
	}
	
	public function __get($name)
	{
		switch($name)
		{
			case "sRealHostname":
				return $this->GetHostname();
				break;
			default:
				return null;
				break;
		}
	}
	
	public function GetHostname()
	{
		return $this->ssh->RunCommandCached("hostname2", true)->stdout;
	}
}

?>
