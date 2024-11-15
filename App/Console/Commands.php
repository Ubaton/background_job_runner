<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BackgroundJobs\BackgroundJobRunner;

class RunBackgroundJob extends Command
{
    protected $signature = 'background-job:run {jobId}';
    protected $description = 'Run a background job by ID';

    public function handle(BackgroundJobRunner $runner)
    {
        $jobId = $this->argument('jobId');
        
        try {
            $runner->executeJob($jobId);
        } catch (\Exception $e) {
            $this->error("Failed to execute job {$jobId}: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}