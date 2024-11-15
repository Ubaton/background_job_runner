# Custom Background Job Runner - Laravel Project Documentation

## Table of Contents

1. [Introduction](#introduction)
2. [System Requirements](#system-requirements)
3. [Installation](#installation)
4. [Project Structure](#project-structure)
5. [Configuration](#configuration)
6. [Usage Guide](#usage-guide)
7. [Background Job System](#background-job-system)
8. [API Documentation](#api-documentation)
9. [Testing](#testing)
10. [Troubleshooting](#troubleshooting)
11. [Contributing](#contributing)
12. [License](#license)

## Introduction

This Laravel project implements a custom background job processing system that operates independently of Laravel's built-in queue system. It provides a robust solution for executing long-running tasks asynchronously with features like error handling, job retries, and status monitoring.

### Key Features

- Custom background job processing
- Error handling and automatic retries
- Job status monitoring
- Priority-based job execution
- Delayed job execution
- Web-based dashboard for job monitoring
- Cross-platform compatibility (Windows & Unix)

## System Requirements

- PHP >= 8.1
- Composer
- MySQL >= 5.7 or PostgreSQL >= 9.6
- Node.js & NPM (for frontend assets)
- Git

## Installation

1. Clone the repository:

```bash
git clone https://github.com/your-username/your-project.git
cd your-project
```

2. Install PHP dependencies:

```bash
composer install
```

3. Copy environment file and generate key:

```bash
cp .env.example .env
php artisan key:generate
```

4. Configure your database in `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

5. Run migrations:

```bash
php artisan migrate
```

6. Install frontend dependencies:

```bash
npm install
npm run dev
```

## Project Structure

```
├── app/
│   ├── Console/
│   │   └── Commands/
│   │       └── RunBackgroundJob.php
│   ├── Services/
│   │   └── BackgroundJobs/
│   │       └── BackgroundJobRunner.php
│   ├── Http/
│   │   └── Controllers/
│   └── helpers.php
├── config/
├── database/
├── resources/
├── routes/
└── tests/
```

## Configuration

### Background Job Configuration

Configure allowed job classes in `BackgroundJobRunner.php`:

```php
private const ALLOWED_CLASSES = [
    'App\\Jobs\\',
    'App\\Services\\',
];
```

### Logging Configuration

Add these channels to `config/logging.php`:

```php
'channels' => [
    'background_jobs' => [
        'driver' => 'daily',
        'path' => storage_path('logs/background-jobs.log'),
        'level' => 'debug',
        'days' => 14,
    ],
    'background_jobs_errors' => [
        'driver' => 'daily',
        'path' => storage_path('logs/background-jobs-errors.log'),
        'level' => 'error',
        'days' => 14,
    ],
],
```

## Usage Guide

### Running Background Jobs

Basic usage:

```php
// Simple job execution
$jobId = runBackgroundJob(ProcessOrder::class, 'handle', ['orderId' => 123]);

// With priority and delay
$jobId = runBackgroundJob(
    SendEmail::class,
    'send',
    ['to' => 'user@example.com'],
    priority: 5,
    delay: 60 // 60 seconds delay
);
```

### Creating a Job Class

```php
namespace App\Jobs;

class ProcessOrder
{
    public function handle(int $orderId)
    {
        // Process the order
        // Long-running task logic here
        return true;
    }
}
```

### Checking Job Status

```php
use Illuminate\Support\Facades\Cache;

$jobData = Cache::get("background_job:{$jobId}");
$status = $jobData['status'] ?? null;

// Possible status values:
// - pending
// - running
// - completed
// - failed
// - pending_retry
```

## Background Job System

### Job Lifecycle

1. Job is dispatched using `runBackgroundJob()`
2. System validates the job class and method
3. Job is stored in cache with a unique ID
4. Background process is spawned to execute the job
5. Job execution is logged
6. Status is updated throughout the process

### Retry Mechanism

Jobs will automatically retry up to 3 times with a 5-second delay between attempts if they fail. Configure these settings in `BackgroundJobRunner.php`:

```php
private const MAX_RETRIES = 3;
private const RETRY_DELAY = 5; // seconds
```

## API Documentation

### Helper Function

```php
function runBackgroundJob(
    string $className,
    string $methodName = 'handle',
    array $parameters = [],
    int $priority = 1,
    int $delay = 0
): string
```

Parameters:

- `$className`: Full class name including namespace
- `$methodName`: Method to execute (defaults to 'handle')
- `$parameters`: Array of parameters to pass to the method
- `$priority`: Job priority (1-10, higher = higher priority)
- `$delay`: Delay in seconds before execution

Returns: Unique job ID string

## Testing

Run the test suite:

```bash
php artisan test
```

Create a test job:

```php
use Tests\TestCase;

class BackgroundJobTest extends TestCase
{
    public function test_can_run_background_job()
    {
        $jobId = runBackgroundJob(TestJob::class, 'handle', ['test' => true]);
        $this->assertNotNull($jobId);

        // Wait for job to complete
        sleep(2);

        $jobData = Cache::get("background_job:{$jobId}");
        $this->assertEquals('completed', $jobData['status']);
    }
}
```

## Troubleshooting

Common issues and solutions:

1. **Job Not Running**

   - Check file permissions
   - Verify PHP path in BackgroundJobRunner
   - Check error logs

2. **Permission Errors**

   - Ensure storage directory is writable
   - Check log file permissions

3. **Job Failing**
   - Check background_jobs_errors.log
   - Verify class exists and is autoloadable
   - Confirm method exists and is callable

## Contributing

1. Fork the repository
2. Create a feature branch
3. Commit your changes
4. Push to the branch
5. Create a Pull Request
