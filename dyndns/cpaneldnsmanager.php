<?php

/**
 * A class to handle DNS entries in cPanel
 *
 * @param mixed[] $cpanelInfo Array of cPanel configuration values:
 * 		$cpanelInfo['hostname']
 *		$cpanelInfo['port']
 *		$cpanelInfo['username']
 *		$cpanelInfo['password']
 *		$cpanelInfo['zone']			// example.com
 *		$cpanelInfo['subdomain']	// remote
 * @param boolean $sslVerify Set to false to disable verification of cPanel SSL certificates
 */
class CpanelDnsManager implements IDnsManager, ITimeable
{
	private $curl;
	private $cpanelUrl;
	private $zoneDomain;
	private $subDomain;
	private $executionTime;

	/***** Constructor / Destructor *****/

	function __construct($cpanelInfo, $sslVerify = true)
	{
		$startTime = microtime(true);

		$this->curl = curl_init();
		if ($this->curl === false)
		{
			throw new \Exception('Failed to initialize cURL');
		}

		/* Store the result instead of displaying it */
		curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);

		/* Treat HTTP codes > 400 as failures */
		curl_setopt($this->curl, CURLOPT_FAILONERROR, true);

		if ($sslVerify === false)
		{
			/* Allow self-signed certs */
			curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, false);
			/* Allow certs that do not match the hostname */
			curl_setopt($this->curl, CURLOPT_SSL_VERIFYHOST, false);
		}

		/* Configure the authentication parameters */
		curl_setopt($this->curl, CURLOPT_HTTPHEADER, array('Authorization: Basic '.base64_encode($cpanelInfo['username'].':'.$cpanelInfo['password'])));

		$this->cpanelUrl = sprintf('https://%s:%d', $cpanelInfo['hostname'], $cpanelInfo['port']);
		$this->zoneDomain = $cpanelInfo['zone'];
		$this->subDomain = $cpanelInfo['subdomain'];

		$this->executionTime = (microtime(true) - $startTime);
	}

	function __destruct()
	{
		curl_close($this->curl);
	}

	/***** Public Functions *****/

	public function createHost($host, $ip)
	{
		throw new \Exception(IDnsManager::NOT_IMPLEMENTED);
	}

	public function readHost($host)
	{
		$startTime = microtime(true);
		$hostRecords = $this->getHostRecords($host);

		if (!empty($hostRecords))
		{
			$dynHost = new \DynDnsHost();
			$dynHost->name = $host;
			$dynHost->ttl = $hostRecords[0]['ttl'];
			$dynHost->lastTouched = '';
			$dynHost->lastUpdated = '';

			/* Iterate through the records to get each address */
			foreach($hostRecords as $record)
			{
				if ($record['type'] == 'A')
				{
					$dynHost->ipv4Address = $record['address'];
				}
				elseif ($record['type'] == 'AAAA')
				{
					$dynHost->ipv6Address = $record['address'];
				}
				else
				{
					/* Unsupported record type */
				}
			}
		}
		else
		{
			$dynHost = null;
		}

		$this->executionTime += (microtime(true) - $startTime);
		return $dynHost;
	}

	public function updateHost($host, $ip)
	{
		$startTime = microtime(true);
		$updated = false;

		$hostRecords = $this->getHostRecords($host);
		if (empty($hostRecords))
		{
			throw new \Exception(IDnsManager::INVALID_HOST);
		}

		if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
		{
			$recordType = 'A';
		}
		elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6))
		{
			$recordType = 'AAAA';
		}
		else
		{
			throw new \Exception(IDnsManager::INVALID_IP_ADDRESS);
		}

		foreach ($hostRecords as $record)
		{
			if (($record['type'] == $recordType) && ($record['address'] != $ip))
			{
				$updateParams = array(
					'cpanel_jsonapi_module' => 'ZoneEdit',
					'cpanel_jsonapi_func' => 'edit_zone_record',
					'domain' => $this->zoneDomain,
					'line' => $record['line'],
					'type' => $record['type'],
					'address' => $ip
				);

				$result = $this->cpanelRequest($updateParams);
				if ($result)
				{
					$updated = true;
				}
			}
		}

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

	/***** Private Functions *****/

	private function getHostRecords($host)
	{
		$fetchzoneParams = array(
			'cpanel_jsonapi_module' => 'ZoneEdit',
			'cpanel_jsonapi_func' => 'fetchzone_records',
			'domain' => $this->zoneDomain,
			'name' => sprintf('%s.%s.%s.', $host, $this->subDomain, $this->zoneDomain),
			'customonly' => 1
		);

		$result = $this->cpanelRequest($fetchzoneParams);
		if (!isset($result['data']))
		{
			throw new \Exception('No zone data returned');
		}

		return $result['data'];
	}

	private function cpanelRequest($params)
	{
		curl_setopt($this->curl, CURLOPT_URL, $this->cpanelUrl.'/json-api/cpanel?'.http_build_query($params));

		$result = curl_exec($this->curl);
		if ($result === false)
		{
			throw new \Exception(curl_error($this->curl));
		}

		/* Attempt to process result */
		$jsonResult = json_decode($result, true);
		if (isset($jsonResult['cpanelresult']))
		{
			/* Descend into the result tree */
			$jsonResult = $jsonResult['cpanelresult'];
		}
		else
		{
			throw new \Exception('Could not decode JSON response');
		}

		return $jsonResult;
	}
}

?>
