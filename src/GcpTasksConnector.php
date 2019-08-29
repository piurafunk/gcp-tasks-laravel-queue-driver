<?php

namespace Piurafunk\GcpTasksQueueDriver;

use Illuminate\Queue\Connectors\ConnectorInterface;

class GcpTasksConnector implements ConnectorInterface
{
	/**
	 * Establish a queue connection.
	 *
	 * @param array $config
	 *
	 * @return \Illuminate\Contracts\Queue\Queue
	 */
	public function connect(array $config)
	{
		return new GcpTasksQueue($config);
	}
}
