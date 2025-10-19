# Platform.sh API client

This is a PHP library for accessing the Platform.sh API.

We recommend you use the [Platform.sh CLI](https://github.com/platformsh/cli) (which uses this library) for most purposes.

### Versions

- The `3.x` branch (major version 3) requires PHP 8.2 and above.
- The `2.x` branch (major version 2) requires PHP 7.2.5 and above.
  This branch is no longer maintained.
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
use Platformsh\Client\Connection\Connector;
use Platformsh\Client\PlatformClient;

// Set up configuration.
$connector = new Connector([
    'api_url' => 'https://api.platform.sh',
    'accounts' => 'https://api.platform.sh/',
    'centralized_permissions_enabled' => true,
]);

// Initialize the client.
$client = new PlatformClient();

// Set the API token to use.
//
// N.B. you must keep your API token(s) safe!
$client->getConnector()->setApiToken($myToken, 'exchange');

// Get a project.
$project = $client->getProject('my_project_id');
if ($project) {
    // Get the default (production) environment.
    $environment = $project->getEnvironment($project->default_branch);

    // Create a new environment.
    $result = $environment->runOperation('branch', body: ['name' => 'sprint-1', 'title' => 'Sprint 1']);

    // Wait for the operation to complete.
    $activities = $result->getActivities();
    while (count($activities) > 0) {
        foreach ($activities as $key => $activity) {
            if ($activity->isComplete() || $activity->state === \Platformsh\Client\Model\Activity::STATE_CANCELLED) {
                unset($activities[$key]);
            } else {
                echo "Waiting for the activity: {$activity->getDescription()}\n";
                $activity->wait(function () { echo '.'; });
                echo "\n";
            }
        }
    }

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
