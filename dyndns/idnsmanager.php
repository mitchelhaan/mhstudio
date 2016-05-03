<?php

interface IDnsManager
{
	const INVALID_HOST = 'Invalid Host';
	const INVALID_IP_ADDRESS = 'Invalid IP Address';
	const NOT_IMPLEMENTED = 'Not Implemented';

	/**
	 * Creates a new host with the specified name and IP address
	 *
	 * @param string $host Name of the host
	 * @param string $ip IP address of the host
	 *
	 * @return bool true if the host was created, false if the creation failed
	 */
	public function createHost($host, $ip);

	/**
	 * Read the specified host's information
	 *
	 * @param string $host Name of the host
	 *
	 * @return DynDnsHost object representing the requested host or NULL if the host wasn't found
	 */
	public function readHost($host);

	/**
	 * Updates the specified host
	 *
	 * @param string $host Name of the host
	 * @param string $ip New IP address of the host
	 *
	 * @return bool true if the host was updated, false if the update failed or the IP address didn't change
	 */
	public function updateHost($host, $ip);

	/**
	 * Deletes the specified host
	 *
	 * @param string $host Name of the host
	 *
	 * @return bool true if the host was deleted, false if the deletion failed
	 */
	public function deleteHost($host);
}

?>
