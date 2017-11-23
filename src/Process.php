<?php
/****************************************************
 *                     naruto                       *
 *                                                  *
 * An object-oriented multi process manager for PHP *
 *                                                  *
 *                    TIERGB                        *
 *           <https://github.com/TIGERB>            *
 *                                                  *
 ****************************************************/

namespace Naruto;

use Naruto\ProcessException;
use Closure;

/**
 * process abstract class
 */
abstract class Process
{
	/**
	 * current process type such as master and worker
	 *
	 * @var string
	 */
	protected $type = '';

	/**
	 * process id
	 *
	 * @var int
	 */
	public	  $pid = '';

	/**
	 * pipe name
	 *
	 * @var string
	 */
	protected $pipeName = '';

	/**
	 * pipe mode
	 *
	 * @var integer
	 */
	protected $pipeMode = 0777;

	/**
	 * pipe name prefix
	 *
	 * @var string
	 */
	protected $pipeNamePrefix = 'naruto.pipe';

	/**
	 * the folder for pipe file store
	 *
	 * @var string
	 */
	protected $pipeDir = '/tmp/';
	
	/**
	 * pipe file path
	 *
	 * @var string
	 */
	protected $pipePath = '';

	/**
	 * the byte size read from pipe
	 *
	 * @var integer
	 */
	protected $readPipeType = 1024;

	/**
	 * hangup sleep time unit:second /s
	 *
	 * @var int
	 */
	const LOOP_SLEEP_TIME = 1;

	/**
	 * construct function
	 */
	public function __construct()
	{
		if (empty($this->pid)) {
			$this->pid = posix_getpid();
		}
		$this->pipeName = $this->pipeNamePrefix . $this->pid;
		$this->pipePath = $this->pipeDir . $this->pipeName;
	}

	/**
	 * hungup abstract funtion
	 *
	 * @param Closure $closure
	 * @return void
	 */
	abstract protected function hangup(Closure $closure);

	/**
	 * create pipe
	 *
	 * @return void
	 */
	public function pipeMake()
	{
		if (! file_exists($this->pipePath)) {
			if (! posix_mkfifo($this->pipePath, $this->pipeMode)) {
				ProcessException::error([
					'msg' => [
						'from'  => $this->type,
						'extra' => "pipe make {$this->pipePath}"
					]
				]);
				exit;
			}
			chmod($this->pipePath, $this->pipeMode);
			ProcessException::info([
				'msg' => [
					'from'  => $this->type,
					'extra' => "pipe make {$this->pipePath}"
				]
			]);
		}
	}

	/**
	 * write msg to the pipe
	 *
	 * @return void
	 */
	public function pipeWrite($signal = '')
	{
		$pipe = fopen($this->pipePath, 'w');
		if (! $pipe) {
			ProcessException::error([
				'msg' => [
					'from'  => $this->type,
					'extra' => "pipe open {$this->pipePath}"
				]
			]);
			return;
		}

		ProcessException::info([
			'msg' => [
					'from'  => $this->type,
					'extra' => "pipe open {$this->pipePath}"
				]
		]);
		
		$res = fwrite($pipe, $signal);
		if (! $res) {
			ProcessException::error([
				'msg' => [
					'from'  => $this->type,
					'extra' => "pipe write {$this->pipePath}",
					'signal'=> $signal,
					'res'   => $res
				]
			]);
			return;
		}

		ProcessException::info([
			'msg' => [
					'from'  => $this->type,
					'extra' => "pipe write {$this->pipePath}"
				]
		]);

		if (! fclose($pipe)) {
			ProcessException::error([
				'msg' => [
					'from'  => $this->type,
					'extra' => "pipe close {$this->pipePath}"
				]
			]);
			return;
		}

		ProcessException::info([
			'msg' => [
					'from'  => $this->type,
					'extra' => "pipe close {$this->pipePath}"
				]
		]);
	}

	/**
	 * read msg from the pipe
	 *
	 * @return void
	 */
	public function pipeRead()
	{
		// check pipe
		while (! file_exists($this->pipePath)) {
			sleep(self::LOOP_SLEEP_TIME);
		}

		// open pipe
		do {
			$workerPipe = fopen($this->pipePath, 'r+'); // The "r+" allows fopen to return immediately regardless of external  writer channel. 
			sleep(self::LOOP_SLEEP_TIME);
		} while (! $workerPipe);

		// set pipe switch a non blocking stream
		stream_set_blocking($workerPipe, false);

		// read pipe
		if ($msg = fread($workerPipe, $this->readPipeType)) {
			ProcessException::info([
				'msg' => [
					'from'  => $this->type,
					'extra' => "pipe read {$this->pipePath}"
				]
			]);
		}
		return $msg;
	}

	/**
	 * clear pipe file
	 *
	 * @return void
	 */
	protected function clearPipe()
	{
		$msg = [
			'msg' => [
				'from'  => $this->type,
				'extra' => "pipe clear {$this->pipePath}"
			]
		];
		ProcessException::info($msg);
		if (! unlink($this->pipePath)) {
			ProcessException::error($msg);
			return false;
		}
		return true;
	}

	/**
	 * stop this process
	 *
	 * @return void
	 */
	public function stop()
	{
		$msg = [
			'msg' => [
				'from'  => $this->type,
				'extra' => "{$this->pid} stop"
			]
		];
		ProcessException::info($msg);
		if (! posix_kill($this->pid, SIGKILL)) {
			ProcessException::error($msg);
			return false;
		}
		return true;
	}

	/**
	 * set this process name
	 *
	 * @return void
	 */
	protected function setProcessName()
	{
		cli_set_process_title('naruto master');
	}
}
