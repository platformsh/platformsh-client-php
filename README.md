# Platform.sh API client

This will be a PHP library for accessing the Platform.sh API.

Current usage:
```php
$client = new \Platformsh\Client\PlatformClient();

$connector = $client->getConnector();

// Store session data in a file (allows token reuse).
$session = $connector->getSession();
$session->setStorage(new \Platformsh\Client\Session\Storage\File());

// Enable Guzzle debugging.
$connector->setDebug(true);

// Log in (bypassed if there is a token available).
$connector->authenticate($username, $password);

// Get all the user's projects.
$projects = $client->getProjects();

foreach ($projects as $project) {
  // Get the master environment.
  $master = $project->getEnvironment('master');
  // Branch the master environment.
  $activity = $master->branch('Sprint 1', 'sprint-1');
  // Wait for the activity to complete.
  $activity->wait();
  // Get the new branch.
  $sprint1 = $project->getEnvironment('sprint-1');
  // etc.
  break;
}

```
