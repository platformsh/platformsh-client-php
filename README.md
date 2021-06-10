# Platform.sh API client

This is a PHP library for accessing the Platform.sh API.

We recommend you use the [Platform.sh CLI](https://github.com/platformsh/platformsh-cli) (which uses this library) for most purposes.

[![Build Status](https://travis-ci.org/platformsh/platformsh-client-php.svg?branch=master)](https://travis-ci.org/platformsh/platformsh-client-php)

### Versions

- The `2.x` branch (major version 2) requires PHP 7.1.0 and above.
- The `1.x` branch (any version &lt; 2) supports PHP 5.5.9 and above, and uses Guzzle 5.
  Old PHP versions are supported by the [Platform.sh CLI](https://github.com/platformsh/platformsh-cli), which
  is why this branch is still maintained.

## Install

```sh
composer require platformsh/client
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

Creating a project:

```php
use \Platformsh\Client\Model\Subscription\SubscriptionOptions;

$subscription = $client->createSubscription(SubscriptionOptions::fromArray([
    'project_region' => 'uk-1.platform.sh',
    'project_title' => 'My project',
    'plan' => 'development',
    'default_branch' => 'main',
]));

echo "Created subscription $subscription->id, waiting for it to activate...\n";

$subscription->wait();

$project = $subscription->getProject();

echo "The project is now active: $project->id\n";
echo "Git URI: " . $project->getGitUrl() . "\n";
```
