<?php

namespace Piurafunk\GcpTasksQueueDriver;

use Illuminate\Container\Container;
use Illuminate\Queue\Jobs\Job;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Support\Arr;

class GcpTasksJob extends Job implements JobContract
{
	/**
	 * @var string
	 */
	protected $rawJob;

	/**
	 * @var array
	 */
	protected $job;

	/**
	 * @var \Piurafunk\GcpTasksQueueDriver\GcpTasksQueue
	 */
	protected $gcpTasksQueue;

	/**
	 * @var string
	 */
	protected $queueName;

	public function __construct(Container $container, GcpTasksQueue $gcpTasksQueue, $job, $connectionName, $queue)
	{
		$this->rawJob = $job;
		$this->job = json_decode($job, true);
		$this->queue = $queue;
		$this->gcpTasksQueue = $gcpTasksQueue;
		$this->container = $container;
		$this->connectionName = $connectionName;
		$this->queueName = $this->gcpTasksQueue->getTasksClient()->queueName(
			$this->gcpTasksQueue->getProject(),
			$this->gcpTasksQueue->getLocation(),
			$this->gcpTasksQueue->getQueue()
		);
	}

	/**
	 * Get the job identifier.
	 *
	 * @return string
	 */
	public function getJobId()
	{
		return Arr::last(explode('/', request()->header('X-AppEngine-TaskName')));
	}

	/**
	 * Get the raw body of the job.
	 *
	 * @return string
	 */
	public function getRawBody()
	{
		return $this->rawJob;
	}

	/**
	 * Get the number of times the job has been attempted.
	 *
	 * @return int
	 */
	public function attempts()
	{
		return request()->header('X-AppEngine-TaskExecutionCount');
	}

	/**
	 * @param int $delay
	 *
	 * @throws \Google\ApiCore\ApiException
	 */
	public function release($delay = 0)
	{
		$options = [];

		if ($delay > 0) {
			$options['scheduleTime'] = $delay;
		}

		$this->gcpTasksQueue->pushRaw(base64_encode($this->rawJob), $this->queue, $options);
		$this->delete();

		parent::release($delay);
	}

	/**
	 * @throws \Google\ApiCore\ApiException
	 */
	public function delete()
	{
		$this->gcpTasksQueue->getTasksClient()->deleteTask($this->gcpTasksQueue->getTasksClient()->queueName(
				$this->gcpTasksQueue->getProject(),
				$this->gcpTasksQueue->getLocation(),
				$this->gcpTasksQueue->getQueue()
			) . "/tasks/{$this->getJobId()}");
		parent::delete();
	}
}
