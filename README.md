# Platform.sh API client

This is a PHP library for accessing the Platform.sh API.

## Install

Add these requirements to your `composer.json` file:
```
        "commerceguys/guzzle-oauth2-plugin": "@beta",
        "platformsh/client": "@beta"
```

## Usage

Example:
```php

use Platformsh\Client\PlatformClient;

// Initialize the client.
$client = new PlatformClient();

// Set the API token to use.
$connector = $client->getConnector();
$connector->setApiToken($myToken);

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
