<?php

namespace Yzalis\Components\Crontab;

use Yzalis\Components\Crontab\Job;
use Symfony\Component\Process\Process;

/**
 * Represent a crontab
 *
 * @author Benjamin Laugueux <benjamin@yzalis.com>
 */
class Crontab
{
    /**
     * A collection of jobs
     *
     * @var array $jobs  Yzalis\Compoenents\Crontab\Job
     */
    private $jobs = array();

    /**
     * Location of the crontab executable
     *
     * @var string
     */
    public $crontabExecutable = '/usr/bin/crontab';

    /**
     * The user executing the comment 'crontab'
     *
     * @var string
     */
    protected $user = null;

    /**
     * The error when using the comment 'crontab'
     *
     * @var string
     */
    protected $error;

    /**
     * The output when using the command 'crontab'
     *
     * @var string
     */
    protected $output;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->parseExistingCrontab();
    }

    /**
     * Parse an existing crontab
     * 
     * @return Crontab
     */
    public function parseExistingCrontab()
    {
        var_dump($this->crontabCommand());
        // parsing cron file
        $process = new Process($this->crontabCommand() . ' -l');
        $process->run();
        $lines = array_filter(explode(PHP_EOL, $process->getOutput()), function($line) {
            return '' != trim($line);
        });

        foreach ($lines as $lineNumber => $line) {
            // if line is nt a comment, convert it to a cron
            if (0 !== \strpos($line, '#', 0)) {
                $job = Job::parse($line);
            }
            $this->addJob($job);
        }

        $this->error = $process->getErrorOutput();

        return $this;
    }

    /**
     * Calcuates crontab command
     *
     * @return string
     */
    protected function crontabCommand()
    {
        $cmd = $this->getCrontabExecutable();
        if ($this->getUser()) {
            $cmd .= sprintf(' -u %s ', $this->getUser());
        }

        return $cmd;
    }

    /**
     * Render the crontab and associated jobs
     *
     * @return string
     */
    public function render()
    {
        return implode(PHP_EOL, $this->getJobs());
    }

    /**
     * Write the current crons in the cron table
     */
    public function write()
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'cron');

        file_put_contents($tmpFile, $this->render() . PHP_EOL);

        $process = new Process($this->getCrontabExecutable() . ' ' . $tmpFile);
        $process->run();

        $this->error = $process->getErrorOutput();
        $this->output = $process->getOutput();

        return $this;
    }

    /**
     * Remove all crontab content
     * 
     * @return Crontab
     */
    public function flush()
    {
        $this->removeAllJobs();
        $this->write();
    }

    /**
     * Get unix user to add crontab
     *
     * @return string
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Set unix user to add crontab
     *
     * @param string $user
     *
     * @return Crontab
     */
    public function setUser($user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get crontab executable location
     *
     * @return string
     */
    public function getCrontabExecutable()
    {
        return $this->crontabExecutable;
    }

    /**
     * Set unix user to add crontab
     *
     * @param string $crontabExecutable
     *
     * @return Crontab
     */
    public function setCrontabExecutable($crontabExecutable)
    {
        $this->crontabExecutable = $crontabExecutable;

        return $this;
    }

    /**
     * Get all crontab jobs
     *
     * @return array An array of Yzalis\Components\Job
     */
    public function getJobs()
    {
        return $this->jobs;
    }

    /**
     * Get crontab error
     *
     * @return string
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * Get crontab output
     *
     * @return string
     */
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * Add a new job to the crontab
     *
     * @param Yzalis\Components\Job $job
     *
     * @return Crontab
     */
    public function addJob(Job $job)
    {
        $this->jobs[$job->getHash()] = $job;

        return $this;
    }

    /**
     * Adda new job to the crontab
     *
     * @param array $jobs
     *
     * @return Crontab
     */
    public function setJobs(array $jobs)
    {
        foreach ($jobs as $job) {
            $this->addJob($job);
        }

        return $this;
    }

    /**
     * Remove all job in the current crontab
     *
     * @return Crontab
     */
    public function removeAllJobs()
    {
        $this->jobs = array();

        return $this;
    }

    /**
     * Remove a specified job in the current crontab
     *
     * @param $job
     *
     * @return Crontab
     */
    public function removeJob($job)
    {
        unset($this->jobs[$job->getHash()]);

        return $this;
    }
}
