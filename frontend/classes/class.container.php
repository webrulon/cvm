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

class Container extends CPHPDatabaseRecordClass
{
	public $table_name = "containers";
	public $fill_query = "SELECT * FROM containers WHERE `Id` = '%d'";
	public $verify_query = "SELECT * FROM containers WHERE `Id` = '%d'";
	
	public $prototype = array(
		'string' => array(
			'Hostname'		=> "Hostname",
			'InternalId'		=> "InternalId",
			'RootPassword'		=> "RootPassword"
		),
		'numeric' => array(
			'NodeId'		=> "NodeId",
			'TemplateId'		=> "TemplateId",
			'UserId'		=> "UserId",
			'VirtualizationType'	=> "VirtualizationType",
			'DiskSpace'		=> "DiskSpace",
			'GuaranteedRam'		=> "GuaranteedRam",
			'BurstableRam'		=> "BurstableRam",
			'CpuCount'		=> "CpuCount",
			'Status'		=> "Status",
			'IncomingTrafficUsed'	=> "IncomingTrafficUsed",
			'IncomingTrafficLast'	=> "IncomingTrafficLast",
			'OutgoingTrafficUsed'	=> "OutgoingTrafficUsed",
			'OutgoingTrafficLast'	=> "OutgoingTrafficLast",
			'IncomingTrafficLimit'	=> "IncomingTrafficLimit",
			'OutgoingTrafficLimit'	=> "OutgoingTrafficLimit",
			'TotalTrafficLimit'	=> "TotalTrafficLimit"
		),
		'node' => array(
			'Node'			=> "NodeId"
		),
		'template' => array(
			'Template'		=> "TemplateId"
		),
		'user' => array(
			'User'			=> "UserId"
		)
	);
	
	public function __get($name)
	{
		switch($name)
		{
			case "sRamUsed":
				return $this->GetRamUsed();
				break;
			case "sRamTotal":
				return $this->GetRamTotal();
				break;
			case "sDiskUsed":
				return $this->GetDiskUsed();
				break;
			case "sDiskTotal":
				return $this->GetDiskTotal();
				break;
			case "sBandwidthUsed":
				return $this->GetBandwidthUsed();
				break;
			case "sCurrentStatus":
				return (int)$this->GetCurrentStatus();
				break;
			case "sStatusText":
				return $this->GetStatusText();
				break;
			default:
				return parent::__get($name);
				break;
		}
	}
	
	public function GetBandwidthUsed()
	{
		return ($this->sOutgoingTrafficUsed + $this->IncomingTrafficUsed) / (1024 * 1024);
	}
	
	public function GetCurrentStatus()
	{
		if($this->sStatus == CVM_STATUS_SUSPENDED || $this->sStatus == CVM_STATUS_TERMINATED)
		{
			return $this->sStatus;
		}
		else
		{
			$command = array("vzctl", "status", $this->sInternalId);
			
			$result = $this->sNode->ssh->RunCommandCached($command, false);
			
			if($result->returncode == 0)
			{
				$values = split_whitespace($result->stdout);
				
				if($values[4] == "running")
				{
					return CVM_STATUS_STARTED;
				}
				else
				{
					return CVM_STATUS_STOPPED;
				}
			}
		}
	}
	
	public function GetStatusText()
	{
		$status = $this->sCurrentStatus;
	
		if($status == CVM_STATUS_STARTED)
		{
			return "running";
		}
		elseif($status == CVM_STATUS_STOPPED)
		{
			return "stopped";
		}
		elseif($status == CVM_STATUS_SUSPENDED)
		{
			return "suspended";
		}
		else
		{
			return "unknown";
		}
	}
	
	public function GetRamUsed()
	{
		$ram = $this->GetRam();
		return $ram['used'];
	}
	
	public function GetRamTotal()
	{
		$ram = $this->GetRam();
		return $ram['total'];
	}
	
	public function GetRam()
	{
		$result = $this->RunCommandCached("free -m", true);
		$lines = explode("\n", $result->stdout);
		array_shift($lines);
		
		$total_free = 0;
		$total_used = 0;
		$total_total = 0;

		foreach($lines as $line)
		{
			$line = trim($line);
			$values = split_whitespace($line);
			
			if(trim($values[0]) == "Mem:")
			{
				$total_total = $values[1];
				$total_used = $values[2];
				$total_free = $values[3];
			}
			
		}
		
		return array(
			'free'	=> $total_free,
			'used'	=> $total_used,
			'total'	=> $total_total
		);
	}
	
	public function GetDiskUsed()
	{
		$disk = $this->GetDisk();
		return $disk['used'];
	}
	
	public function GetDiskTotal()
	{
		$disk = $this->GetDisk();
		return $disk['total'];
	}
	
	public function GetDisk()
	{
		$result = $this->RunCommandCached("df -l -x tmpfs", true);
		$lines = explode("\n", $result->stdout);
		array_shift($lines);
		
		$total_free = 0;
		$total_used = 0;
		$total_total = 0;
		
		foreach($lines as $disk)
		{
			$disk = trim($disk);
			
			if(!empty($disk))
			{
				$values = split_whitespace($disk);
				$total_free += (int)$values[3] / 1024;
				$total_used += (int)$values[2] / 1024;
				$total_total += ((int)$values[2] + (int)$values[3]) / 1024;
			}
		}
		
		return array(
			'free'	=> $total_free,
			'used'	=> $total_used,
			'total'	=> $total_total
		);
	}
	
	public function CheckAllowed()
	{
		if($this->sStatus == CVM_STATUS_SUSPENDED)
		{
			throw new ContainerSuspendedException("No operations can be performed on this VPS beacuse it is suspended.", 1, $this->sInternalId);
		}
		elseif($this->sStatus == CVM_STATUS_TERMINATED)
		{
			throw new ContainerSuspendedException("No operations can be performed on this VPS beacuse it is terminated.", 1, $this->sInternalId);
		}
		else
		{
			return true;
		}
	}
	
	public function SetOptions($options)
	{
		if(is_array($options))
		{
			$command_elements = array("vzctl", "set", $this->sInternalId);
			
			foreach($options as $key => $value)
			{
				$command_elements[] = "--{$key}";
				$command_elements[] = $value;
			}
			
			$command_elements[] = "--save";
			
			$this->sNode->ssh->RunCommand($command_elements, true);
		}
		else
		{
			throw new InvalidArgumentException("The option argument to Container::SetOptions should be an array.");
		}
	}
	
	public function RunCommand($command, $throw_exception = false)
	{
		return $this->sNode->ssh->RunCommand(array("vzctl", "exec", $this->sInternalId, $command), $throw_exception);
	}
	
	public function RunCommandCached($command, $throw_exception = false)
	{
		return $this->sNode->ssh->RunCommandCached(array("vzctl", "exec", $this->sInternalId, $command), $throw_exception);
	}
	
	public function Deploy($conf = array())
	{
		$sRootPassword = random_string(20);
		
		$this->uRootPassword = $sRootPassword;
		$this->InsertIntoDatabase();
		
		$command = array("vzctl", "create", $this->sInternalId, "--ostemplate", $this->sTemplate->sTemplateName);
		
		$result = $this->sNode->ssh->RunCommand($command, false);
		$result->returncode = 0;

		if($result->returncode == 0 && strpos($result->stderr, "ERROR") === false)
		{
			$this->uStatus = CVM_STATUS_CREATED;
			$this->InsertIntoDatabase();
			
			$dummy_processes = 1000;
			$dummy_files = $this->sGuaranteedRam * 32;
			$dummy_sockets = $this->sGuaranteedRam * 3;
			
			$sKMemSize = (isset($conf['sKMemSize'])) 		? $conf['sKMemSize'] : 		(40 * 1024 * ($dummy_processes / 2)) + ($dummy_files * 384 * 100);
			$sKMemSizeLimit = (isset($conf['sKMemSizeLimit'])) 	? $conf['sKMemSizeLimit'] : 	(int)($sKMemSize * 1.1);
			$sLockedPages = (isset($conf['sLockedPages'])) 		? $conf['sLockedPages'] : 	$dummy_processes;
			$sShmPages = (isset($conf['sShmPages'])) 		? $conf['sShmPages'] : 		$this->sGuaranteedRam . "M";
			$sOomGuarPages = (isset($conf['sOomGuarPages'])) 	? $conf['sOomGuarPages'] : 	$this->sGuaranteedRam . "M";
			$sTcpSock = (isset($conf['sTcpSock'])) 			? $conf['sTcpSock'] : 		$dummy_sockets;
			$sOtherSock = (isset($conf['sOtherSock'])) 		? $conf['sOtherSock'] : 	$dummy_sockets;
			$sFLock = (isset($conf['sFLock'])) 			? $conf['sFLock'] : 		$dummy_processes;
			$sFLockLimit = (isset($conf['sFLockLimit'])) 		? $conf['sFLockLimit'] : 	(int)($sFLock * 1.1);
			$sTcpSndBuf = (isset($conf['sTcpSndBuf'])) 		? $conf['sTcpSndBuf'] : 	round($this->sGuaranteedRam * 1024 / 5) . "K";
			$sTcpRcvBuf = (isset($conf['sTcpRcvBuf'])) 		? $conf['sTcpRcvBuf'] : 	round($this->sGuaranteedRam * 1024 / 5) . "K";
			$sOtherBuf = (isset($conf['sOtherBuf'])) 		? $conf['sOtherBuf'] : 		round($this->sGuaranteedRam * 1024 / 5) . "K";
			$sOtherBufLimit = (isset($conf['sOtherBufLimit'])) 	? $conf['sOtherBufLimit'] : 	(int)($sOtherBuf + (2 * $dummy_processes * 16));
			$sTcpSndBufLimit = (isset($conf['sTcpSndBufLimit'])) 	? $conf['sTcpSndBufLimit'] : 	(int)($sTcpSndBuf + (2 * $dummy_processes * 16));
			$sTcpRcvBufLimit = (isset($conf['sTcpRcvBufLimit'])) 	? $conf['sTcpRcvBufLimit'] : 	(int)($sTcpRcvBuf + (2 * $dummy_processes * 16));
			$sDgramBuf = (isset($conf['sDgramBuf'])) 		? $conf['sDgramBuf'] : 		round($this->sGuaranteedRam * 1024 / 5) . "K";
			$sNumFile = (isset($conf['sNumFile'])) 			? $conf['sNumFile'] : 		$dummy_files;
			$sNumProc = (isset($conf['sNumProc'])) 			? $conf['sNumProc'] : 		$dummy_processes;
			$sDCache = (isset($conf['sDCache'])) 			? $conf['sDCache'] : 		$dummy_files * 384;
			$sDCacheLimit = (isset($conf['sDCacheLimit'])) 		? $conf['sDCacheLimit'] : 	(int)($sDCache * 1.1);
			$sAvgProc = (isset($conf['sAvgProc'])) 			? $conf['sAvgProc'] : 		$dummy_processes / 2;
			
			$command = array("vzctl", "set", $this->sInternalId,
				"--onboot", "yes",
				"--setmode", "restart",
				"--hostname", $this->sHostname,
				"--nameserver", "8.8.8.8",
				"--nameserver", "8.8.4.4",
				"--numproc", $this->sCpuCount,
				"--vmguarpages", "{$this->sGuaranteedRam}M:unlimited",
				"--privvmpages", "{$this->sBurstableRam}M:{$this->sBurstableRam}M",
				"--quotatime", "0",
				"--diskspace", "{$this->sDiskSpace}M:{$this->sDiskSpace}M",
				"--userpasswd", "root:{$sRootPassword}",
				"--kmemsize", "{$sKMemSize}:{$sKMemSizeLimit}",
				"--lockedpages", "{$sLockedPages}:{$sLockedPages}",
				"--shmpages", "{$sShmPages}:{$sShmPages}",
				"--physpages", "0:unlimited",
				"--oomguarpages", "{$sOomGuarPages}:unlimited",
				"--numtcpsock", "{$sTcpSock}:{$sTcpSock}",
				"--numflock", "{$sFLock}:{$sFLockLimit}",
				"--numpty", "32:32",
				"--numsiginfo", "512:512",
				"--tcpsndbuf", "{$sTcpSndBuf}:{$sTcpSndBufLimit}",
				"--tcprcvbuf", "{$sTcpRcvBuf}:{$sTcpRcvBufLimit}",
				"--othersockbuf", "{$sOtherBuf}:{$sOtherBufLimit}",
				"--dgramrcvbuf", "{$sDgramBuf}:{$sDgramBuf}",
				"--numothersock", "{$sOtherSock}:{$sOtherSock}",
				"--numfile", "{$sNumFile}:{$sNumFile}",
				"--numproc", "{$sNumProc}:{$sNumProc}",
				"--dcachesize", "{$sDCache}:{$sDCacheLimit}",
				"--numiptent", "128:128",
				"--diskinodes", "200000:220000",
				"--avnumproc", "{$sAvgProc}:{$sAvgProc}",
				"--save"
			);
			
			/* 
			This may be useful if we turn out to have a kernel that supports vswap
			
			$command = shrink_command("vzctl set {$this->sInternalId}
				--onboot yes
				--setmode restart
				--hostname {$this->sHostname}
				--nameserver 8.8.8.8
				--nameserver 8.8.4.4
				--numproc {$this->sCpuCount}
				--quotatime 0
				--diskspace {$this->sDiskSpace}M:{$this->sDiskSpace}M
				--userpasswd root:{$sRootPassword}
				--numtcpsock 360:360
				--numflock 188:206
				--numpty 16:16
				--numsiginfo 256:256
				--tcpsndbuf 1720320:2703360
				--tcprcvbuf 1720320:2703360
				--othersockbuf 1126080:2097152
				--dgramrcvbuf 262144:262144
				--numothersock 360:360
				--numfile 9312:9312
				--dcachesize 3409920:3624960
				--numiptent 128:128
				--diskinodes 200000:220000
				--avnumproc 180:180
				--ram {$this->sGuaranteedRam}M
				--swap {$this->sBurstableRam}M
				--save
			");*/
			
			$result = $this->sNode->ssh->RunCommand($command, false);
			
			if($result->returncode == 0)
			{
				$this->uStatus = CVM_STATUS_CONFIGURED;
				$this->InsertIntoDatabase();
				
				return true;
			}
			else
			{
				throw new ContainerConfigureException($result->stderr, $result->returncode, $this->sInternalId);
			}
		}
		else
		{
			throw new ContainerCreateException($result->stderr, $result->returncode, $this->sInternalId);
		}
	}
	
	public function Destroy()
	{
		if($this->sCurrentStatus == CVM_STATUS_STARTED)
		{
			$this->Stop();
		}
		
		$command = array("vzctl", "destroy", $this->sInternalId);
		$result = $this->sNode->ssh->RunCommand($command, false);
		
		if($result->returncode == 0)
		{
			return true;
		}
		else
		{
			throw new ContainerDestroyException("Destroying VPS failed: {$result->stderr}", $result->returncode, $this->sInternalId);
		}
	}
	
	public function Reinstall()
	{
		try
		{
			$this->Destroy();
			$this->Deploy();
		}
		catch (ContainerDestroyException $e)
		{
			throw new ContainerReinstallException("Reinstalling VPS failed during destroying: " . $e->getMessage(), $e->getCode(), $this->sInternalId, $e);
		}
		catch (ContainerCreateException $e)
		{
			throw new ContainerReinstallException("Reinstalling VPS failed during creation: " . $e->getMessage(), $e->getCode(), $this->sInternalId, $e);
		}
		catch (ContainerConfigureException $e)
		{
			throw new ContainerReinstallException("Reinstalling VPS failed during configuration: " . $e->getMessage(), $e->getCode(), $this->sInternalId, $e);
		}
	}
	
	public function Start($forced = false)
	{
		if($this->sStatus == CVM_STATUS_SUSPENDED && $forced == false)
		{
			throw new ContainerSuspendedException("The VPS cannot be started as it is suspended.", 1, $this->sInternalId);
		}
		elseif($this->sStatus == CVM_STATUS_TERMINATED && $forced == false)
		{
			throw new ContainerTerminatedException("The VPS cannot be started as it is terminated.", 1, $this->sInternalId);
		}
		else
		{
			$command = array("vzctl", "start", $this->sInternalId);
			$result = $this->sNode->ssh->RunCommand($command, false);
			
			if($result->returncode == 0)
			{
				$this->uStatus = CVM_STATUS_STARTED;
				$this->InsertIntoDatabase();
				return true;
			}
			else
			{
				throw new ContainerStartException($result->stderr, $result->returncode, $this->sInternalId);
			}
		}
	}
	
	public function Stop()
	{
		if($this->sStatus == CVM_STATUS_SUSPENDED)
		{
			throw new ContainerSuspendedException("The VPS cannot be stopped as it is suspended.", 1, $this->sInternalId);
		}
		elseif($this->sStatus == CVM_STATUS_TERMINATED)
		{
			throw new ContainerTerminatedException("The VPS cannot be stopped as it is terminated.", 1, $this->sInternalId);
		}
		else
		{
			$command = array("vzctl", "stop", $this->sInternalId);
			$result = $this->sNode->ssh->RunCommand($command, false);
			
			// vzctl is retarded enough to return exit status 0 when the command fails because the container isn't running, so we'll have to check the stderr for specific error string(s) as well. come on guys, it's 2012.
			if($result->returncode == 0 && strpos($result->stderr, "Unable to stop") === false)
			{
				$this->uStatus = CVM_STATUS_STOPPED;
				$this->InsertIntoDatabase();
				return true;
			}
			else
			{
				throw new ContainerStopException($result->stderr, $result->returncode, $this->sInternalId);
			}
		}
	}
	
	public function Suspend()
	{
		if($this->sStatus != CVM_STATUS_SUSPENDED)
		{
			try
			{
				$this->Stop();
				$this->uStatus = CVM_STATUS_SUSPENDED;
				$this->InsertIntoDatabase();
			}
			catch (ContainerStopException $e)
			{
				throw new ContainerSuspendException("Suspension failed as the VPS could not be stopped.", 1, $this->sInternalId, $e);
			}
		}
		else
		{
			throw new ContainerSuspendException("The VPS is already suspended.", 1, $this->sInternalId);
		}
	}
	
	public function Unsuspend()
	{
		if($this->sStatus == CVM_STATUS_SUSPENDED)
		{
			try
			{
				$this->Start(true);
				$this->uStatus = CVM_STATUS_STARTED;
				$this->InsertIntoDatabase();
			}
			catch (ContainerStartException $e)
			{
				throw new ContainerUnsuspendException("Unsuspension failed as the VPS could not be started.", 1, $this->sInternalId, $e);
			}
		}
		else
		{
			throw new ContainerUnsuspendException("The VPS is not suspended.", 1, $this->sInternalId);
		}
	}
	
	public function AddIp($ip)
	{
		$command = array("vzctl", "set", $this->sInternalId, "--ipadd", $ip, "--save");
		
		$result = $this->sNode->ssh->RunCommand($command, false);
		
		if($result->returncode == 0)
		{
			return true;
		}
		else
		{
			throw new ContainerIpAddException($result->stderr, $result->returncode, $this->sInternalId);
		}
	}
	
	public function RemoveIp($ip)
	{
		$command = array("vzctl", "set", $this->sInternalId, "--ipdel", $ip, "--save");
		
		$result = $this->sNode->ssh->RunCommand($command, false);
		
		if($result->returncode == 0)
		{
			return true;
		}
		else
		{
			throw new ContainerIpRemoveException($result->stderr, $result->returncode, $this->sInternalId);
		}
	}
	
	public function UpdateTraffic()
	{
		/* TODO: Don't rely on grep, and parse the output in this function itself. Also try to find another way to do this without relying
		 * on the container at all. */
		$result = $this->sNode->ssh->RunCommand(array("vzctl", "exec", $this->sInternalId, "cat /proc/net/dev | grep venet0"), false);
		
		if($result->returncode == 0)
		{
			$lines = split_lines($result->stdout);
			$values = split_whitespace(str_replace(":", " ", $lines[0]));
			
			$uIncoming = $values[1];
			$uOutgoing = $values[9];
			
			if($uIncoming < (int)$this->sIncomingTrafficLast || $uOutgoing < (int)$this->sOutgoingTrafficLast)
			{
				// the counter has reset (wrap-around, reboot, etc.)
				$uNewIncoming = $uIncoming;
				$uNewOutgoing = $uOutgoing;
			}
			else
			{
				$uNewIncoming = $uIncoming - $this->sIncomingTrafficLast;
				$uNewOutgoing = $uOutgoing - $this->sOutgoingTrafficLast;
			}
			
			$this->uIncomingTrafficUsed = $this->sIncomingTrafficUsed + $uNewIncoming;
			$this->uOutgoingTrafficUsed = $this->sOutgoingTrafficUsed + $uNewOutgoing;
			
			$this->uIncomingTrafficLast = $uIncoming;
			$this->uOutgoingTrafficLast = $uOutgoing;
			
			$this->InsertIntoDatabase();
		}
		else
		{
			throw new ContainerTrafficRetrieveException($result->stderr, $result->returncode, $this->sInternalId);
		}
	}
	
	public function SetRootPassword($password)
	{
		if($this->sStatus == CVM_STATUS_SUSPENDED)
		{
			throw new ContainerSuspendedException("The root password cannot be changed, because the VPS is suspended.", 1, $this->sInternalId);
		}
		elseif($this->sStatus == CVM_STATUS_TERMINATED)
		{
			throw new ContainerTerminatedException("The root password cannot be changed, because the VPS is terminated.", 1, $this->sInternalId);
		}
		else
		{
			$this->SetOptions(array(
				'userpasswd'	=> "root:{$password}"
			));
		}
	}
	
	public function EnableTunTap()
	{
		/* TODO: Finish EnableTunTap function, check whether tun module is available on host */
		$command = array("vzctl", "set", $this->sInternalId, "--devnodes", "net/tun:rw", "--save");
	}
}
