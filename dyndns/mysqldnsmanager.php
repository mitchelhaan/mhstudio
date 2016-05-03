<?php

/**
 * A class to handle caching DNS entries in MySQL
 *
 * @param string[] $mysqlInfo Array of MySQL configuration values:
 * 		$mysqlInfo['hostname']
 *		$mysqlInfo['username']
 *		$mysqlInfo['password']
 *		$mysqlInfo['database']
 *		$mysqlInfo['table']
 */
class MysqlDnsManager implements IDnsManager, ITimeable
{
	private $dbHandle;
	private $dbTable;
	private $executionTime;

	/***** Constructor / Destructor *****/

	function __construct($mysqlInfo)
	{
		$startTime = microtime(true);

		$this->dbHandle = new \PDO("mysql:host=${mysqlInfo['hostname']};dbname=${mysqlInfo['database']}", $mysqlInfo['username'], $mysqlInfo['password']);
		$this->dbHandle->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		$this->dbTable = $mysqlInfo['table'];

		$this->executionTime = (microtime(true) - $startTime);
	}

	function __destruct()
	{
		$this->dbHandle = null;
	}

	/***** Public Functions *****/

	public function createHost($host, $ip)
	{
		throw new \Exception(IDnsManager::NOT_IMPLEMENTED);
	}

	public function readHost($host)
	{
		$startTime = microtime(true);

		$stHandle = $this->dbHandle->prepare("SELECT * FROM $this->dbTable WHERE name = :name LIMIT 1");
		$stHandle->setFetchMode(\PDO::FETCH_CLASS, '\DynDnsHost');
		$stHandle->bindParam(':name', $host, \PDO::PARAM_STR);
		$stHandle->execute();

		$dynHost = $stHandle->fetch();

		$this->executionTime += (microtime(true) - $startTime);
		return $dynHost;
	}

	public function updateHost($host, $ip)
	{
		$dynHost = $this->readHost($host);
		$updated = false;

		$startTime = microtime(true);

		if (empty($dynHost))
		{
			throw new \Exception(IDnsManager::INVALID_HOST);
		}

		if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
		{
			$addrType = 'ipv4Address';
		}
		elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6))
		{
			$addrType = 'ipv6Address';
		}
		else
		{
			throw new \Exception(IDnsManager::INVALID_IP_ADDRESS);
		}

		if ($ip != $dynHost->$addrType)
		{
			$stHandle = $this->dbHandle->prepare("UPDATE $this->dbTable SET $addrType = :ip, lastUpdated = NOW(), lastTouched = lastUpdated WHERE name = :name LIMIT 1");
			$stHandle->bindParam(':ip', $ip, \PDO::PARAM_STR);
			$updated = true;
		}
		else
		{
			$stHandle = $this->dbHandle->prepare("UPDATE $this->dbTable SET lastTouched = NOW() WHERE name = :name LIMIT 1");
		}

		$stHandle->bindParam(':name', $host, \PDO::PARAM_STR);
		$stHandle->execute();

		$this->executionTime += (microtime(true) - $startTime);
		return $updated;
	}

	public function deleteHost($host)
	{
		throw new \Exception(IDnsManager::NOT_IMPLEMENTED);
	}

	public function getExecutionTime()
	{
		return $this->executionTime;
	}
}

?>
