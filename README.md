# Platform.sh API client

This is a PHP library for accessing the Platform.sh API.

## Install

```
composer require platformsh/client:dev-master
```

## Usage

Example:
```php
// Include the Composer autoload file.
require 'vendor/autoload.php';

// Initialize the client.
$client = new \Platformsh\Client\PlatformClient();

$connector = $client->getConnector();

// Store session data in a file (allows token reuse).
$session = $connector->getSession();
$session->setStorage(new \Platformsh\Client\Session\Storage\File());

// Log in (bypassed if there is a token available).
$connector->authenticate($username, $password);

// Get the user's first project.
$project = $client->getProjects()[0];

// Get the master environment.
$master = $project->getEnvironment('master');

// Branch the master environment.
$activity = $master->branch('Sprint 1', 'sprint-1');

// Wait for the activity to complete.
$activity->wait();

// Get the new branch.
$sprint1 = $project->getEnvironment('sprint-1');

```
