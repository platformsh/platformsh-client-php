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
//   - 'exchange' for all newer API tokens (created after April 2016)
//   - 'access' for older 'personal access tokens'.
$client->getConnector()->setApiToken($myToken, 'exchange');

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
