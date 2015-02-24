# Platform.sh API client

This is a PHP library for accessing the Platform.sh API.

## Install

Add these requirements to your `composer.json` file:
```
        "commerceguys/guzzle-oauth2-plugin": "dev-master@dev",
        "platformsh/client": "~0.1"
```

## Usage

Example:
```php

use Platformsh\Client\PlatformClient;
use Platformsh\Client\Session\Storage\File;

// Initialize the client.
$client = new PlatformClient();

$connector = $client->getConnector();

// Store session data in a file (allows token reuse).
$session = $connector->getSession();
$session->setStorage(new File());

// Log in (bypassed if already logged in).
$connector->authenticate($username, $password);

// Get the user's first project.
$projects = $client->getProjects();
if ($projects) {
    $firstProject = $projects[0];

    // Get the master environment.
    $master = $firstProject->getEnvironment('master');

    // Branch the master environment.
    $activity = $master->branch('Sprint 1', 'sprint-1');

    // Wait for the activity to complete.
    $activity->wait();

    // Get the new branch.
    $sprint1 = $project->getEnvironment('sprint-1');
}
```
