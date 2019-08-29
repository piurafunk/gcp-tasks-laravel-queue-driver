<?php

namespace Piurafunk\GcpTasksQueueDriver;

use Carbon\Carbon;
use Google\Cloud\Tasks\V2\AppEngineHttpRequest;
use Google\Cloud\Tasks\V2\CloudTasksClient;
use Google\Cloud\Tasks\V2\Task;
use Google\Protobuf\Timestamp;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Queue\Queue;
use Illuminate\Support\Arr;

class GcpTasksQueue extends Queue implements QueueContract
{
	/**
	 * @var \Google\Cloud\Tasks\V2\CloudTasksClient
	 */
	protected $tasksClient;

	/**
	 * @var string
	 */
	protected $project;

	/**
	 * @var string
	 */
	protected $location;

	/**
	 * @var string
	 */
	protected $queue;

	/**
	 * @var string
	 */
	protected $parent;

	/**
	 * @var string
	 */
	protected $relativeUrl;

	/**
	 * @var string
	 */
	protected $currentJobContainerKey;

	/**
	 * @var array
	 */
	protected $options = [
		'scheduleTime',
	];

	/**
	 * GcpTasksQueue constructor.
	 *
	 * @param $config
	 *
	 * @throws \Google\ApiCore\ValidationException
	 */
	public function __construct($config)
	{
		$this->tasksClient = new CloudTasksClient($config);
		$this->project = $config['project_id'];
		$this->location = $config['location'];
		$this->queue = $config['queue'];
		$this->parent = $this->tasksClient->queueName($this->project, $this->location, $this->queue);

		$this->relativeUrl = $config['relative_url'] ?? '/';
		$this->currentJobContainerKey = $config['current_job_container_key'] ?? 'current_job';
	}

	/**
	 * Get the size of the queue.
	 *
	 * @param string|null $queue
	 *
	 * @return int
	 * @throws \Google\ApiCore\ApiException
	 * @throws \Google\ApiCore\ValidationException
	 */
	public function size($queue = null)
	{
		$count = 0;
		$parent = $this->tasksClient->queueName($this->project, $this->location, $queue);
		$iterator = $this->tasksClient->listTasks($parent)->iterateAllElements();

		while ($iterator->valid()) {
			$count++;
			$iterator->next();
		}

		return $count;
	}

	/**
	 * Push a new job onto the queue.
	 *
	 * @param string|object $job
	 * @param mixed         $data
	 * @param string|null   $queue
	 *
	 * @return mixed
	 * @throws \Google\ApiCore\ApiException
	 */
	public function push($job, $data = '', $queue = null)
	{
		return $this->pushRaw($this->createPayload($job, $queue, $data), $queue);
	}

	/**
	 * Push a raw payload onto the queue.
	 *
	 * @param string      $payload
	 * @param string|null $queue
	 * @param array       $options
	 *
	 * @return mixed
	 * @throws \Google\ApiCore\ApiException
	 */
	public function pushRaw($payload, $queue = null, array $options = [])
	{
		$task = (new Task)
			->setName($this->parent . '/tasks/' . uniqid())
			->setAppEngineHttpRequest((new AppEngineHttpRequest)
				->setBody($payload)
				->setRelativeUri($this->relativeUrl)
			);

		// Set options on the task
		foreach ($this->options as $option) {
			if (array_key_exists($option, $options)) {
				$this->{'set' . ucfirst($option)}($task, $options[$option]);
			}
		}

		$response = $this->tasksClient->createTask($this->parent, $task);

		return Arr::last(explode('/', $response->getName()));
	}

	/**
	 * Push a new job onto the queue after a delay.
	 *
	 * @param \DateTimeInterface|\DateInterval|int $delay
	 * @param string|object                        $job
	 * @param mixed                                $data
	 * @param string|null                          $queue
	 *
	 * @return mixed
	 * @throws \Google\ApiCore\ApiException
	 */
	public function later($delay, $job, $data = '', $queue = null)
	{
		return $this->pushRaw(
			$this->createPayload($job, $queue, $data),
			$queue,
			['scheduleTime' => $delay]
		);
	}

	/**
	 * Pop the next job off of the queue.
	 *
	 * @param string $queue
	 *
	 * @return \Illuminate\Contracts\Queue\Job|null
	 * @throws \Illuminate\Contracts\Container\BindingResolutionException
	 */
	public function pop($queue = null)
	{
		$payload = $this->container->make($this->currentJobContainerKey);

		// Return the previously bound in job content, provided by GCP Tasks
		return new GcpTasksJob(
			$this->container,
			$this,
			$payload,
			$this->connectionName,
			$this->queue
		);
	}

	/**
	 * @inheritDoc
	 */
	protected function createPayload($job, $queue, $data = '')
	{
		return base64_encode(parent::createPayload($job, $queue, $data));
	}

	public function getTasksClient()
	{
		return $this->tasksClient;
	}

	public function getProject()
	{
		return $this->project;
	}

	public function getLocation()
	{
		return $this->location;
	}

	public function getQueue()
	{
		return $this->queue;
	}

	/**
	 * @param \Google\Cloud\Tasks\V2\Task $task
	 * @param                             $time
	 */
	protected function setScheduleTime(&$task, $time)
	{
		$time = Carbon::createFromTimestamp($this->availableAt($time));
		$timestamp = new Timestamp;
		$timestamp->fromDateTime($time);
		$task->setScheduleTime($timestamp);
	}
}
