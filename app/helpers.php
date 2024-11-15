<?php

if (!function_exists('runBackgroundJob')) {
    /**
     * Run a job in the background
     *
     * @param string $className The full class name
     * @param string $methodName The method to execute (defaults to 'handle')
     * @param array $parameters Parameters to pass to the method
     * @param int $priority Job priority (1-10, higher number = higher priority)
     * @param int $delay Delay in seconds before the job starts
     * @return string Job ID
     */
    function runBackgroundJob(
        string $className,
        string $methodName = 'handle',
        array $parameters = [],
        int $priority = 1,
        int $delay = 0
    ): string {
        $runner = app(App\Services\BackgroundJobs\BackgroundJobRunner::class);
        return $runner->dispatch($className, $methodName, $parameters, $priority, $delay);
    }
}