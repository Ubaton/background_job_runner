<?php

namespace App\Services\BackgroundJobs;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;

class BackgroundJobRunner
{
    private const ALLOWED_CLASSES = [
        // Add your allowed class names here
        'App\\Jobs\\',
        'App\\Services\\',
    ];

    private const LOG_FILE = 'background_jobs.log';
    private const ERROR_LOG_FILE = 'background_jobs_errors.log';
    private const MAX_RETRIES = 3;
    private const RETRY_DELAY = 5; // seconds

    /**
     * Run a job in the background
     *
     * @param string $className
     * @param string $methodName
     * @param array $parameters
     * @param int $priority
     * @param int $delay
     * @return string Job ID
     */
    public function dispatch(
        string $className,
        string $methodName = 'handle',
        array $parameters = [],
        int $priority = 1,
        int $delay = 0
    ): string {
        $this->validateJob($className, $methodName);
        
        $jobId = uniqid('job_', true);
        $jobData = [
            'id' => $jobId,
            'class' => $className,
            'method' => $methodName,
            'parameters' => $parameters,
            'priority' => $priority,
            'delay' => $delay,
            'attempts' => 0,
            'status' => 'pending',
            'created_at' => now(),
        ];

        // Store job data in cache
        Cache::put("background_job:{$jobId}", $jobData);

        // Create the background process
        $this->executeInBackground($jobId);

        return $jobId;
    }

    /**
     * Execute the job in a background process
     */
    private function executeInBackground(string $jobId): void
    {
        $phpBinary = PHP_BINARY;
        $artisanPath = base_path('artisan');
        
        $command = [
            $phpBinary,
            $artisanPath,
            'background-job:run',
            $jobId,
        ];

        if (PHP_OS_FAMILY === 'Windows') {
            $process = new Process($command);
            $process->start();
        } else {
            $processCommand = implode(' ', array_map('escapeshellarg', $command)) . ' > /dev/null 2>&1 & echo $!';
            exec($processCommand);
        }
    }

    /**
     * Execute a specific job
     */
    public function executeJob(string $jobId): void
    {
        $jobData = Cache::get("background_job:{$jobId}");
        
        if (!$jobData) {
            throw new Exception("Job not found: {$jobId}");
        }

        try {
            $this->updateJobStatus($jobId, 'running');
            
            // Handle delay if specified
            if ($jobData['delay'] > 0) {
                sleep($jobData['delay']);
            }

            $instance = app($jobData['class']);
            $result = $instance->{$jobData['method']}(...$jobData['parameters']);

            $this->updateJobStatus($jobId, 'completed');
            $this->logSuccess($jobId, $jobData);
            
        } catch (Exception $e) {
            $this->handleJobFailure($jobId, $jobData, $e);
        }
    }

    /**
     * Handle job failure and implement retry logic
     */
    private function handleJobFailure(string $jobId, array $jobData, Exception $e): void
    {
        $jobData['attempts']++;
        $jobData['last_error'] = $e->getMessage();
        
        if ($jobData['attempts'] < self::MAX_RETRIES) {
            $jobData['status'] = 'pending_retry';
            Cache::put("background_job:{$jobId}", $jobData);
            
            // Wait before retry
            sleep(self::RETRY_DELAY);
            $this->executeInBackground($jobId);
            
        } else {
            $jobData['status'] = 'failed';
            Cache::put("background_job:{$jobId}", $jobData);
            $this->logError($jobId, $jobData, $e);
        }
    }

    /**
     * Validate the job class and method
     */
    private function validateJob(string $className, string $methodName): void
    {
        // Validate class name
        if (!$this->isClassAllowed($className)) {
            throw new Exception("Class {$className} is not allowed to run as background job");
        }

        // Validate method exists
        if (!method_exists($className, $methodName)) {
            throw new Exception("Method {$methodName} does not exist in class {$className}");
        }
    }

    /**
     * Check if the class is allowed to run as a background job
     */
    private function isClassAllowed(string $className): bool
    {
        foreach (self::ALLOWED_CLASSES as $allowedPrefix) {
            if (str_starts_with($className, $allowedPrefix)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Update job status
     */
    private function updateJobStatus(string $jobId, string $status): void
    {
        $jobData = Cache::get("background_job:{$jobId}");
        if ($jobData) {
            $jobData['status'] = $status;
            $jobData['updated_at'] = now();
            Cache::put("background_job:{$jobId}", $jobData);
        }
    }

    /**
     * Log successful job execution
     */
    private function logSuccess(string $jobId, array $jobData): void
    {
        Log::channel('background_jobs')->info("Job completed successfully", [
            'job_id' => $jobId,
            'class' => $jobData['class'],
            'method' => $jobData['method'],
            'attempts' => $jobData['attempts'],
        ]);
    }

    /**
     * Log job error
     */
    private function logError(string $jobId, array $jobData, Exception $e): void
    {
        Log::channel('background_jobs_errors')->error("Job failed", [
            'job_id' => $jobId,
            'class' => $jobData['class'],
            'method' => $jobData['method'],
            'attempts' => $jobData['attempts'],
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }
}