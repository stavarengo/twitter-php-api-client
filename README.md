# twitter-php-api-client
PHP Client Library for https://developer.twitter.com API

If you are interested in a PHP API for [Twitter](https://developer.twitter.com), that's your library :)

## About It
 
- Depends only on PSRs.
- Optionally use cache to use less requests and speed up response.
- You can use it with any application, either if it uses or not factories from PSR-11.
- It should be very easy to use, since I tried to keep all the source code well documented.
 
## Installation
Install via `composer`.

```
composer require stavarengo/twitter-php-api-client:^0.0
```

## Basic Usage - More complete documentation yet to come

- Use it directly (without a factory).
  ```php
  $client = new \Sta\TwitterPhpApiClient\TwitterClient(null);

  $authResponse = $client->postOauth2Token($consumerKey, $consumerSecret);
  $response = $client->getUsersShow('@username', false, $authResponse->getEntityBearerToken());
  
  var_dump($response->hasError() ? $response->getEntityError() : $response->getEntityUser());
  ```

- Use our default factory (PSR-11).
  ```php
  $client = $container->get(\Sta\TwitterPhpApiClient\Client::class)
  
  $authResponse = $client->postOauth2Token($consumerKey, $consumerSecret);
  $response = $client->getUsersShow('@username', false, $authResponse->getEntityBearerToken());
  
  var_dump($response->hasError() ? $response->getEntityError() : $response->getEntityUser());
  ```
