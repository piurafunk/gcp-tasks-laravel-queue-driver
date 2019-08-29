<?php

namespace Tests\Integration;

use Google\Cloud\Tasks\V2\Task;
use Illuminate\Container\Container;
use Piurafunk\GcpTasksQueueDriver\GcpTasksJob;
use Piurafunk\GcpTasksQueueDriver\GcpTasksQueue;
use Tests\Jobs\EmptyJob;
use Tests\TestCase;

class PushToQueueTest extends TestCase
{
	/**
	 * @var \Piurafunk\GcpTasksQueueDriver\GcpTasksQueue
	 */
	protected $queue;

	/**
	 * @var array
	 */
	protected $config;

	/**
	 * @var \Illuminate\Container\Container
	 */
	protected $container;

	/**
	 * @throws \Google\ApiCore\ValidationException
	 */
	protected function setUp(): void
	{
		parent::setUp();

		$this->config = [
			'project_id' => env('GCP_PROJECT_ID'),
			'location' => env('GCP_LOCATION'),
			'queue' => env('GCP_QUEUE'),
			'keyFile' => env('GCP_TASKS_CREDENTIALS_PATH', __DIR__ . '/../gcp-tasks-credentials.json'),
			'relative_url' => env('GCP_TASKS_RELATIVE_URL', '/'),
			'current_job_container_key' => env('GCP_CURRENT_JOB_CONTAINER_KEY', 'current_job')
		];

		$this->queue = new GcpTasksQueue([
			'project_id' => $this->config['project_id'],
			'location' => $this->config['location'],
			'queue' => $this->config['queue'],
			'credentialsConfig' => [
				'keyFile' => $this->config['keyFile'],
			],
			'relative_url' => $this->config['relative_url'],
			'current_job_container_key' => $this->config['current_job_container_key'],
		]);

		$this->container = $this->createMock(Container::class);

		$this->queue->setContainer($this->container);
	}

	/**
	 * @throws \Google\ApiCore\ApiException
	 */
	public function testPushToQueue()
	{
		$id = $this->queue->push(new EmptyJob);

		$this->assertIsString($id);

		$task = $this->queue->getTasksClient()->getTask($this->generateTaskName($id));

		$this->assertInstanceOf(Task::class, $task);

		$this->queue->getTasksClient()->deleteTask($this->generateTaskName($id));
	}

	/**
	 * @throws \Google\ApiCore\ApiException
	 * @throws \Illuminate\Contracts\Container\BindingResolutionException
	 */
	public function testPopFromQueue()
	{
		$id = $this->queue->push(new EmptyJob);
		$task = $this->queue->getTasksClient()->getTask($this->generateTaskName($id));

		$this->assertIsString($id);

		$this->container->bind($this->config['current_job_container_key'], function () use ($task) {
			return $task->getAppEngineHttpRequest()->getBody();
		});

		$job = $this->queue->pop();

		$this->assertInstanceOf(GcpTasksJob::class, $job);

		$this->queue->getTasksClient()->deleteTask($this->generateTaskName($id));
	}

	protected function generateTaskName($id)
	{
		return $this->queue->getTasksClient()->queueName(
				$this->config['project_id'],
				$this->config['location'],
				$this->config['queue']
			) . "/tasks/{$id}";
	}
}
