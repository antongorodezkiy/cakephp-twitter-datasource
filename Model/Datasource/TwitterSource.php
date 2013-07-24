<?php
/**
 * Provides a generic connection to the Twitter API
 *
 * This API uses the search endpoint of the Twitter API
 * Search is described here : https://dev.twitter.com/docs/api/1.1/get/search/tweets
 *
 * Read only datasource
 *
 * Based on The InstagramDatasource written by Michael Enger
 * https://github.com/nodesagency/cake-instagram-datasource
 *
 * @package TwitterDatasource
 * @author Rasmus Ebbesen re@nodes.dk
 */
class TwitterSource extends DataSource {

/**
 * description
 *
 * @var string
 */
	public $description = 'Twitter API source';
	protected $_accessToken = null;

	const AUTH_URL = 'https://api.twitter.com/oauth2/token/';
	const API_URL = 'https://api.twitter.com/1.1/';

/**
 * construct the datasource
 *
 * @param array $config
 */
	public function __construct($config) {
		parent::__construct($config);
	}

/**
 * Used to create new records. The "C" CRUD.
 *
 * @param Model $model  The Model to be created.
 * @param array $fields (optional) List of fields to be saved
 * @param array $values (optional) List of values to save
 * @return mixed
 */
	public function create(Model $model, $fields = null, $values = null) {
		switch (get_class($model)) {
		case 'Tweet':
			throw new Exception('Tweet entries do not support create');
			break;
		default:
			throw new Exception('Unhandled model type: ' . get_class($model));
		}
	}


/**
 * Used to read records. The R in CRUD
 *
 * @param Model $model
 * @param array $queryData
 * @param boolean $recursive
 */
	public function read(Model $model, $queryData = array(), $recursive = null) {
		$data = array();
		$limit = !empty($queryData['limit']) ? $queryData['limit'] : 0;
		$offset = !empty($queryData['offset']) ? $queryData['offset'] : 0;
		$resource = !empty($queryData['resource']) ? $queryData['resource'] : 'search/tweets';

		switch (get_class($model)) {
			case 'Tweet':
				// Check Twitter app credentials
				if (!isset($this->config['consumer_key'])) {
					throw new Exception('Invalid consumer key.');
				}

				if (!isset($this->config['consumer_secret'])) {
					throw new Exception('Invalid consumer secret.');
				}

				$conditions = (isset($queryData['conditions'])) ? $this->_extractFields($queryData['conditions'], 'Tweet') : array();

				// determine API endpoint
				$url = sprintf('%s%s.json', self::API_URL, $resource);

				// Autheticate
				$this->_auth();

				// Fetch data
				$data = $this->_request('GET', $url, $conditions);

				if (!empty($data->statuses)) {
					$data = $data->statuses;
				}

				// Arrange data (cakify)
				$data = $this->_wrapResults($data, $model->alias);
				$pagination = !empty($data['pagination']) ? $data['pagination'] : null;
				if (method_exists($model, 'setPagination')) {
					$model->setPagination($pagination);
				}

				// Apply offset
				if (!empty($offset)) {
					if ($offset < count($data)) {
						$data = array_slice($data, $offset);
					} else {
						$queryData['offset'] = $offset - count($data);
						$data = $this->read($model, $queryData);
					}
				}

				// Apply limit
				if (!empty($limit)) {
					if ($limit < count($data)) {
						$data = array_slice($data, 0, $limit);
					} elseif ($limit > count($data)) {
						$queryData['limit'] = $limit - count($data);

						if (!empty($pagination['max_tag_id'])) {
							$queryData['conditions']['max_tag_id'] = $pagination['max_tag_id'];
						} elseif (!empty($pagination['next_max_tag_id'])) {
							$queryData['conditions']['max_tag_id'] = $pagination['next_max_tag_id'];
						} elseif (!empty($pagination['next_max_timestamp'])) {
							$queryData['conditions']['max_timestamp'] = $pagination['next_max_timestamp'];
						} elseif (!empty($pagination['next_max_id'])) {
							$queryData['conditions']['max_id'] = $pagination['next_max_id'];
						}

						if ((!empty($queryData['conditions']['max_tag_id']) ||
							!empty($queryData['conditions']['max_timestamp']) ||
							(
								!empty($queryData['conditions']['max_id'])) &&
								is_array($this->read($model, $queryData))
							)
						) {
							$data = array_merge($data, $this->read($model, $queryData));
						}
					}
				}


				break;
			default:
				throw new Exception('Unhandled model type: ' . get_class($model));
		}

		return $data ?: false;
	}

/**
 * Update a record(s) in the datasource. The "U" CRUD.
 *
 * @param Model $model      Instance of the model class being updated
 * @param array $fields     (optional) List of fields to be updated
 * @param array $values     (optional) List of values to update the $fields to
 * @param array $contitions (optional) Conditions for the update
 * @return boolean
 */
	public function update(Model $model, $fields = null, $values = null, $conditions = null) {
		switch (get_class($model)) {
		case 'Tweet':
			throw new Exception('Tweet entries do not support update');
			break;
		default:
			throw new Exception('Unhandled model type: ' . get_class($model));
		}
	}

/**
 * Delete a record(s) in the datasource. The "D" CRUD.
 *
 * @param Model $model The model class having record(s) deleted
 * @param mixed $id    (optional) ID of the model to delete
 */
	public function delete(Model $model, $id = null) {
		switch (get_class($model)) {
		case 'Tweet':
			throw new Exception('Tweet entries do not support delete');
			break;
		default:
			throw new Exception('Unhandled model type: ' . get_class($model));
		}
	}

/**
 * Caches/returns cached results for child instances
 *
 * @param mixed $data
 * @return array Sources available in this datasource
 */
	public function listSources($data = null) {
		return null; // caching is disabled (for now)
	}

/**
 * Get the list of fields (for example from the conditions array) based on the model name.
 *
 * @param array  $fields Array of keys/values
 * @param string $model  Name of the model to extract fields for
 * @return array
 */
	protected function _extractFields($fields, $model) {
		$temp = array();
		foreach ($fields as $key => $value) {
			if (preg_match('/^' . $model . '\.\w+/', $key)) { // ModelName.fieldName
				$key = substr($key, strlen($model) + 1);
				$temp[$key] = $value;
			} elseif (strpos($key, '.') === false) { // fieldName
				$temp[$key] = $value;
			}
		}

		return $temp;
	}

/**
 * Request an action from the Twitter API.
 *
 * @param string $type   HTTP request type
 * @param string $action Instagram API action
 * @param array  $params (optional) Parameters to send with the request
 * @return array
 */
	protected function _request($type, $url, $params = array()) {
		switch ($type) {
			case 'GET':
				$url = http_build_url($url, array('query' => http_build_query($params)));
				$response = $this->_curl($url);
				break;
			case 'DELETE':
			case 'POST':
			case 'PUT':
				// @todo
			default:
				throw new Exception('Unhandled request type: ' . $type);
		}

		if (!empty($response->errors)) {
			throw new Exception(sprintf('Twitter API failed with error code "%d": %s', $response->errors[0]->code, $response->errors[0]->message));
		}

		return $response;
	}

/**
 * Autheticate with the Twitter api (app only authetication)
 * authentication as described in https://dev.twitter.com/docs/auth/application-only-auth
 *
 * POST request
 *
 * @return string an active auth token
 */
	protected function _auth() {
		if ($this->_accessToken) {
			return $this->_accessToken;
		}
		// encode our credentials
		$encodedKey = base64_encode(rawurlencode($this->config['consumer_key']) . ':' . rawurlencode($this->config['consumer_secret']));

		// defines the body of the request
		$params = array(
			'grant_type' => 'client_credentials'
		);

		$response = $this->_curl(self::AUTH_URL, 'POST', $params, sprintf('Basic %s', $encodedKey));

		// check token type
		if (!isset($response->token_type) || $response->token_type !== 'bearer') {
			throw new Exception('Invalid token type');
		}

		// storing access token
		$this->_accessToken = $response->access_token;

		return $response->access_token;
	}

/**
 * Wrap a list of results in a model.
 *
 * @param array  $results List of results
 * @param string $model   Name of the model
 * @return array
 */
	protected function _wrapResults($results, $model) {
		$temp = array();
		foreach ($results as $entry) {
			$temp[] = array(
				$model => $entry
			);
		}

		return $this->_objectToArray($temp);
	}

/**
 * Converts an object to an array recursively
 *
 * @param object $object
 * @return array
 */
	protected function _objectToArray($object) {
		if (!is_object($object) && !is_array($object)) {
			return $object;
		}

		if (is_object($object)) {
			$object = (array) $object;
		}

		return array_map(array($this, '_objectToArray'), $object);
	}

	protected function _curl($url, $type = 'GET', $params = array(), $authentication = false) {
		// Initialize cURL
		$curl = curl_init($url);

		if ($type == 'POST') {
			// set options
			curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($params));
			curl_setopt($curl, CURLOPT_POST, true);
		}
		if (!$authentication) {
			$authentication = sprintf("Bearer %s", $this->_auth());
		}
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLINFO_HEADER_OUT, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array(
			'Authorization: ' . $authentication,
			'Content-Type: application/x-www-form-urlencoded;charset=UTF-8',
			'Accept-Encoding: gzip'
		));

		// Execute
		$response = json_decode(gzdecode(curl_exec($curl)));

		// check response
		if (!$response) {
			$this->log(curl_error($curl), LOG_DEBUG);
			throw new Exception('An error ocurred, and has been logged');
		}
		// close
		curl_close($curl);

		return $response;
	}
}
