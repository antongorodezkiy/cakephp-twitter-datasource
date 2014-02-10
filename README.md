# Twitter Datasource

Provides a Datasource for connecting to the Twitter API. As of now the datasource is read-only.

## Installation

To use the Twitter API you need to have an application setup. See [the app manager](https://dev.twitter.com/apps) to setup a new app or use an existing one. The Datasource uses the Consumer key and Consumer secret to authenticate with the Twitter API and requires no user login.

Lastly keep in mind the rules and guideline for using Twitter API. Read more about their policies at [Twitter API docs](https://dev.twitter.com/docs)

### Dependencies
* PECL :: Package :: pecl_http

### 1. Get the source

Clone the project to an appropriately named plugin folder:
```
git clone git@github.com:nodesagency/cakephp-twitter-datasource.git app/Plugin/TwitterDatasource
```

Or add it as a submodule to your pre-existing git repository.
```
git submodule add git@github.com:nodesagency/cakephp-twitter-datasource.git app/Plugin/TwitterDatasource
```

### 2. Add the plugin to your project

**app/Config/bootstrap.php**
```php
CakePlugin::load('TwitterDatasource');
```

### 3. Configure the Datasource

The datasource can be configured in the database file.

**app/Config/database.php**
```php
class DATABASE_CONFIG {

	public $tweets = array(
		'datasource'    => 'TwitterDatasource.TwitterSource',
		'consumer_key'     => '', // from your Twitter app settings
		'consumer_secret' => '', // from your Twitter app settings
	);

}
```

Alternatively, it can be added to the ```ConnectionManager``` manually.

```php
App::uses('ConnectionManager', 'Model');

ConnectionManager::create('tweets', array(
	'datasource'    => 'TwitterDatasource.TwitterSource',
	'consumer_key'     => '', // from your Twitter app settings
	'consumer_secret' => '', // from your Twitter app settings
));
```

## Usage

The data source attempts to wrap the [Twitter API](https://dev.twitter.com/docs/api/1.1/) to CakePHP-style models so you can use the ```find()```, ```save()``` and ```delete()``` methods as you normally would. However, the endpoints don't handle the usual parameters (limit, order, etc) so each model acts independendtly.

### Tweet

The Tweet model is a wrapper for the given endpoints, providing access to tweets on Twitter. Calling ```find()``` on this model uses the [/search/tweets](https://dev.twitter.com/docs/api/1.1/get/search/tweets) action as default. It is possbile to access other endpoint on using ```resource``` as parameter.

#### Examples


```php
// Get tweets with a specific hashtag
$hashtag = '#hello #world';

$tweets = $this->Tweet->find(
	'all',
	array(
		'conditions' => array(
			'q' => $hashtag
		)
	)
);

// Get latest tweets of the user 'twitter'
$tweets = $this->Tweet->find(
	'all',
	array(
		'conditions' => array(
			'screen_name' => 'twitter'
		),
		'resource' => 'statuses/user_timeline'
	)
);
```

Basically you should be able to query using all of [these parameters](https://dev.twitter.com/docs/api/1.1/get/search/tweets).

### TweetApi

Contrary to the Tweet model (authentication on the behalf of application). The TweetApi model is authenticate in the behalf of a user. This solution, allows for sending the HTTP request (POST and GET)
against all endpoints of the REST Twitter API (1.1). see [Twitter API](https://dev.twitter.com/docs/api/1.1).

#### Examples
```php
// import the model
App::uses('TweetApi', 'TwitterDatasource');

// remember to use the model
public $uses = array(
	'TwitterDatasource.TweetApi'
);

// build query and make request
$screen_name = 'obama';
$data = $this->TweetApi->find("all",
	array(
		'http_method' => 'GET', // specify the method for accessing the endpoint. It has to be UPERCASE!
		'resource' => 'users/show', // specify endpoint that you want to reach
		'requestParams' => array(
			'screen_name' => $screen_name // specify parameters that the request can handle (check API documentaion)
		),
		'oauth_data' => array( // specify this oAuth data. (previouslt retrived in the authentication proccess)
			'oauth_token'=> $oauth_token, // user's access_token
			'oauth_token_secret' => $oauth_token_secret,
			'consumer_key' => Configure::read('Twitter.consumer_key'), // value taken form the app's settings https://dev.twitter.com/apps/
			'consumer_secret' => Configure::read('Twitter.consumer_secret') // value taken form the app's settings https://dev.twitter.com/apps/
		)
	)
);
```

### Authorization Tweet

Authorization with the Twitter API requires only a reqistered application using ```consumer_key``` and ```consumer_secret```. You need to have set up the ```consumer_key``` and ```consumer_secret``` in the database configuration (see above) and they must match the ones in your Twitter applications manager. ```consumer_key``` and ```consumer_secret``` are submitted to the api alongside the request, authorizing it on the fly.

### Authorization TweetAPI

Because authorization in the behalf of user requires interaction of a user with the application you have to follow the sequence mentioned in [sign in flow](https://dev.twitter.com/docs/auth/implementing-sign-twitter)
Beside of ```consumer_key``` and ```consumer_secret```, you have to obtain ```oauth_token``` (which is a user's access token gathered in 3rd step of Sign-In process) and ```oauth_token_secret``` (which is generated by this [algorithm](https://dev.twitter.com/docs/auth/creating-signature)
and varies with each request)). It is mandatory for a developer to store these keys in order to fetch the data form the API.

### Static Methods
The class contains two static methods used in authorization process. The methods are ```_generateOauthSignature``` - this method is used to generate the oauth signature [signature](https://dev.twitter.com/docs/auth/creating-signature)
and ```_curll``` -that is used to execute POST and GET requests against the Twitter API.
