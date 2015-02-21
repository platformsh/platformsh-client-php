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
  $environments = $project->getEnvironments();
  print_r($environments[0]);
  break;
}

// etc.
```
