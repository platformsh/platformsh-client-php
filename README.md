# Platform.sh API client

This is a PHP library for accessing the Platform.sh API.

## Install

Add this requirement to your `composer.json` file:
```
    "require": {
        "platformsh/client": "@stable"
    }
```

## Usage

Example:
```php

use Platformsh\Client\PlatformClient;

// Initialize the client.
$client = new PlatformClient();

// Set the API token to use.
// N.B. you must keep your API token(s) safe!
$client->getConnector()->setApiToken($myToken);

// Get the user's first project.
$projects = $client->getProjects();
if ($projects) {
    $firstProject = reset($projects);

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
