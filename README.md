# Platform.sh API client

This is a PHP library for accessing the Platform.sh API.

Our API is not stable yet. We recommend you use the [Platform.sh
CLI](https://github.com/platformsh/platformsh-cli) for most purposes.

[![Build Status](https://travis-ci.org/platformsh/platformsh-client-php.svg?branch=1.x)](https://travis-ci.org/platformsh/platformsh-client-php)

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
//
// N.B. you must keep your API token(s) safe!
//
// The second parameter is the token type:
//   - 'exchange' for an API token
//   - 'access' for using an OAuth 2.0 access token directly.
$client->getConnector()->setApiToken($myToken, 'exchange');

// Get the user's first project.
$projects = $client->getProjects();
$project = reset($projects);
if ($project) {
    // Get the default (production) environment.
    $environment = $project->getEnvironment($project->default_branch);

    // Create a new branch.
    $activity = $environment->branch('Sprint 1', 'sprint-1');

    // Wait for the activity to complete.
    $activity->wait();

    // Get the new branch.
    $sprint1 = $project->getEnvironment('sprint-1');
}
```
