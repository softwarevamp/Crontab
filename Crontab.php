<?php

namespace Yzalis\Components\Crontab;

use Yzalis\Components\Crontab\Job;

/**
 * Represent a crontab
 *
 * @author Benjamin Laugueux <benjamin@yzalis.com>
 */
class Crontab
{
    /**
     * A collection of job
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
     * Location to save the temporary crontab file.
     *
     * @var string
     */
    private $tempFile = null;

    /**
     * The user to
     *
     * @var $user
     */
    private $user = null;

    /**
     * An email where crontab execution report will be sent
     *
     * @var $user
     */
    private $mailto = "";

    /**
     * Constructor
     *
     * @return void
     */
    public function __construct()
    {
        $this->generateTempFile();
    }

    /**
     * Destrutor
     */
    public function __destruct()
    {
        if ($this->tempFile && is_file($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    /**
     * Render the crontab and associated jobs
     *
     * @return string
     */
    public function render()
    {
        $content = "";
        if ($this->getMailto()) {
            $content = "MAILTO=" . $this->getMailto() . "\n";
        }
        foreach ($this->getJobs() as $job) {
            $content .= $job->render();
        }

        return $content;
    }



    /**
     * Parse input cron file to cron entires and add them to the current object
     *
     * @param string $filename
     *
     * @return Crontab
     */
    public function addJobsFromFile($filename)
    {
         // check the availability of the file
        $path = realpath($filename);
        if (!$path || !is_readable($path)) {
            throw new \InvalidArgumentException(sprintf('"%s" don\'t exists or isn\'t readable', $filename));
        }

        $content = file_get_contents($path);

        return $this->addJobsFromContent($content);
    }

    public function addJobsFromContent($content)
    {
        $lines = preg_split("/(\r?\n)/", $content);

        if ("" == $lines) {
            throw new \InvalidArgumentException('There is no job to parse.');
        }

        foreach ($lines as $lineno => $line) {
            if ("" == $lines) {
                break;
            }
            try {
                $job = new Job();
                if ($job->parse($line)) {
                   $this->addJob($job);
                }
            } catch (\Exception $e) {
                throw new \InvalidArgumentException(sprintf('Line #%d of file: "%s" is invalid. %s', $lineno, $path, $e));
            }
        }

        return $this;
    }

    public function addJobsFromCrontab()
    {
        $content = $this->getCurrentContabContent();

        return $this->addJobsFromContent($content);
    }

    public function getCurrentContabContent()
    {
        exec("crontab -l > " . $this->tempFile);

        $content = file_get_contents($this->tempFile);

        $this->generateTempFile();

        return $content;
    }

    /**
     * Insert the crontab to the system
     */
    public function write()
    {
        return $this
            ->writeContentInTempFile($this->prepareCrontabContent())
            ->writeTempFileInCrontab()
        ;
    }

    /**
     * Remove all crontab content
     * 
     * @return Crontab
     */
    public function flush()
    {
        return $this
            ->writeContentInTempFile("")
            ->writeTempFileInCrontab()
        ;
    }

    /**
     * Write dynamic content in temporary file
     *
     * @param string $content
     * 
     * @return Crontab
     */
    private function writeContentInTempFile($content)
    {
        $this->generateTempFile();

        file_put_contents($this->tempFile, $content, LOCK_EX);

        return $this;
    }

    /**
     * Write temporary file content in crontab
     * 
     * @return Crontab
     */
    public function writeTempFileInCrontab()
    {
        $out = $this->exec($this->crontabCommand() . ' ' . $this->tempFile . ' 2>&1', $ret);
        if ($ret != 0) {
            throw new \UnexpectedValueException(
                $out . "\n"  . $this->render(), $ret
            );
        }

        return $this;
    }

    /**
     * Prepare crontab rendering
     * 
     * @return string
     */
    private function prepareCrontabContent()
    {
        $date = new \DateTime('now', new \DateTimezone('UTC'));
        $content = "## Auto generated crontab file by https://github.com/yzalis/crontab PHP component " . $date->format('r') . "\n\n";
        $content .= $this->render();

        $currentContent = $this->getCurrentContabContent();

        if (!empty($currentContent)) {
            $lines = preg_split("/(\r?\n)/", $currentContent);
            $content .=  "\n" . "## BEGIN OF ORIGINAL FILE" . "\n";

            foreach ($lines as $line) {
               $content .= sprintf('## %s'."\n", $line);
            }
            $content .= "## END OF ORIGINAL FILE";
        }

        return $content;
    }

    /**
     * Calcuates crontab command
     *
     * @return string
     */
    protected function crontabCommand()
    {
        $cmd = '';
        if ($this->getUser()) {
            $cmd .= sprintf('sudo -u %s ', $this->getUser());
        }
        $cmd .= $this->getCrontabExecutable();

        return $cmd;
    }

    /**
     * Runs command in terminal
     *
     * @param string  $command
     * @param integer $returnVal
     *
     * @return string
     */
    private function exec($command, &$returnVal)
    {
        ob_start();
        system($command, $returnVal);
        $output = ob_get_clean();

        return $output;
    }

    /**
     * Generate temporary crontab file
     *
     * @return Crontab
     */
    protected function generateTempFile()
    {
        if ($this->tempFile && is_file($this->tempFile)) {
            unlink($this->tempFile);
        }
        $tempDir = sys_get_temp_dir();
        $this->tempFile = tempnam($tempDir, 'crontemp');
        chmod($this->tempFile, 0666);

        return $this;
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
     * Get mailto
     *
     * @return string
     */
    public function getMailto()
    {
        return $this->mailto;
    }

    /**
     * Set mailto
     *
     * @param string $mailto
     *
     * @return Crontab
     */
    public function setMailto($mailto)
    {
        if (!filter_var($mailto, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException(sprintf('Mailto "%s" is incorect', $mailto));
        }

        $this->mailto = $mailto;

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
     * Remove all job for current crontab
     *
     * @return Crontab
     */
    public function removeAllJobs()
    {
        $this->jobs = array();

        return $this;
    }

    /**
     * Remove all job for current crontab
     *
     * @return Crontab
     */
    public function removeJob($job)
    {
        unset($this->jobs[$job->getHash()]);

        return $this;
    }
}
