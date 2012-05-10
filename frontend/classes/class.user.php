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

class User extends CPHPDatabaseRecordClass
{
	public $table_name = "users";
	public $fill_query = "SELECT * FROM users WHERE `Id` = '%d'";
	public $verify_query = "SELECT * FROM users WHERE `Id` = '%d'";
	
	public $prototype = array(
		'string' => array(
			'Username'	=> "Username",
			'EmailAddress'	=> "EmailAddress",
			'Hash'		=> "Hash",
			'Salt'		=> "Salt"
		),
		'numeric' => array(
			'AccessLevel'	=> "AccessLevel"
		)
	);
	
	public function GenerateSalt()
	{
		$this->uSalt = random_string(10);
	}
	
	public function GenerateHash()
	{
		if(!empty($this->uSalt))
		{
			if(!empty($this->uPassword))
			{
				$this->uHash = $this->CreateHash($this->uPassword);
			}
			else
			{
				throw new MissingDataException("User object is missing a password.");
			}
		}
		else
		{
			throw new MissingDataException("User object is missing a salt.");
		}
	}
	
	public function CreateHash($input)
	{
		global $settings;
		$hash = crypt($input, "$5\$rounds=50000\${$this->uSalt}{$settings['salt']}$");
		$parts = explode("$", $hash);
		return $parts[4];
	}
	
	public function VerifyPassword($password)
	{
		if($this->CreateHash($password) == $this->sHash)
		{
			return true;
		}
		else
		{
			return false;
		}
	}
}
