<?php

class DynDnsManager implements IDnsManager, ITimeable
{
	private $mysqlDnsManager;
	private $cpanelDnsManager;

	function __construct($cpanelInfo, $mysqlInfo = null)
	{
		if (!empty($mysqlInfo))
		{
			$this->mysqlDnsManager = new MysqlDnsManager($mysqlInfo);
		}
		$this->cpanelDnsManager = new CpanelDnsManager($cpanelInfo);
	}

	function __destruct()
	{

	}

	public function createHost($host, $ip)
	{
		throw new \Exception(IDnsManager::NOT_IMPLEMENTED);
	}

	public function readHost($host)
	{
		$dynHost = null;

		if ($this->mysqlDnsManager instanceof IDnsManager)
		{
			$dynHost = $this->mysqlDnsManager->readHost($host);
		}

		/* Couldn't find it in MySQL? Check cPanel just in case */
		if (empty($dynHost))
		{
			$dynHost = $this->cpanelDnsManager->readHost($host);
		}

		return $dynHost;
	}

	public function updateHost($host, $ip)
	{
		if ($this->mysqlDnsManager instanceof IDnsManager)
		{
			$hostUpdated = $this->mysqlDnsManager->updateHost($host, $ip);
		}
		else
		{
			/* Force the update to go to cPanel since MySQL isn't available */
			$hostUpdated = true;
		}

		/* updateHost returns true if an update was made, so pass the update along */
		if ($hostUpdated === true)
		{
			$hostUpdated = $this->cpanelDnsManager->updateHost($host, $ip);
		}

		return $hostUpdated;
	}

	public function deleteHost($host)
	{
		throw new \Exception(IDnsManager::NOT_IMPLEMENTED);
	}

	public function getExecutionTime()
	{
		$executionTime = 0.0;

		if ($this->mysqlDnsManager instanceof ITimeable)
		{
			$executionTime += $this->mysqlDnsManager->getExecutionTime();
		}

		if ($this->cpanelDnsManager instanceof ITimeable)
		{
			$executionTime += $this->cpanelDnsManager->getExecutionTime();
		}

		return $executionTime;
	}
}

?>
