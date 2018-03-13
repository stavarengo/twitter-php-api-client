<?php
/**
 * Project ${PROJECT_URL}
 *
 * @link      ${GITHUB_URL} Source code
 */

namespace Sta\TwitterPhpApiClient;

use Sta\TwitterPhpApiClient\Middleware\TwitterRequestErrorHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\RequestOptions;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Message\UriInterface;
use Sta\TwitterPhpApiClient\Entity\BearerToken;

class TwitterClient
{
    public const HOST_API = 'https://api.twitter.com/';
    public const VERSION_API = '1.1';
    public const DEFAULT_TWITTER_DATETIME_FORMAT = 'D M d H:i:s O Y';

    /**
     * Forces cache usage even when performing requests that normally would not use it, like POST requests, por
     * instance.
     * The value for this option must be a bool
     */
    public const CACHE_OPT_FORCE_USAGE = 'force-usage';
    /**
     * Change the cache TTL of the item.
     * The value for this option must one of the values you would pass to
     * {@link \Psr\Cache\CacheItemInterface::expiresAfter()}
     */
    public const CACHE_OPT_EXPIRES_AFTER = 'expires-after';

    /**
     * @var \GuzzleHttp\Client
     */
    protected $httpClient;
    /**
     * @var BearerToken
     */
    protected $defaultBearerToken;
    /**
     * @var \Psr\Cache\CacheItemPoolInterface
     */
    private $cachePoll;

    public function __construct(
        TwitterRequestErrorHandler $twitterRequestErrorHandler, ?CacheItemPoolInterface $cachePoll,
        array $guzzleHttpClientConfig = []
    ) {
        $stack = HandlerStack::create();
        $stack->push($twitterRequestErrorHandler->__invoke());

        $defaultGuzzleHttpClientConfig = [
            RequestOptions::TIMEOUT => 120,
            RequestOptions::HTTP_ERRORS => false,
            'handler' => $stack,
        ];

        $this->httpClient = new \GuzzleHttp\Client(
            array_merge(
                $defaultGuzzleHttpClientConfig,
                $guzzleHttpClientConfig
            )
        );

        $this->cachePoll = $cachePoll;
    }

    public function setDefaultBearerToken(BearerToken $bearerToken)
    {
        $this->defaultBearerToken = $bearerToken;
        return $this;
    }

    /**
     * @see https://developer.twitter.com/en/docs/basics/authentication/api-reference/token
     *
     * @param string $consumerKey
     * @param string $consumerSercret
     *
     * @return \Sta\TwitterPhpApiClient\Response
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function postOauth2Token(string $consumerKey, string $consumerSercret): Response
    {
        return $this->request(
            'POST',
            new Uri(self::HOST_API . 'oauth2/token'),
            [
                RequestOptions::BODY => 'grant_type=client_credentials',
                RequestOptions::HEADERS => [
                    'Host' => 'api.twitter.com',
                    'Content-Type' => 'application/x-www-form-urlencoded;charset=UTF-8',
                    'Authorization' => sprintf(
                        'Basic %s',
                        base64_encode(sprintf('%s:%s', rawurlencode($consumerKey), rawurlencode($consumerSercret)))
                    ),
                ],
            ],
            [
                // Second Twitter documentation: "Only one bearer token may exist outstanding for an application, and
                // repeated requests to this method will yield the same already-existent token until it has been
                // invalidated. (...) Tokens received by this method should be cached. If attempted too frequently,
                // requests will be rejected with a HTTP 403 with code 99."
                // See https://developer.twitter.com/en/docs/basics/authentication/api-reference/token
                self::CACHE_OPT_EXPIRES_AFTER => new \DateInterval('P10Y'),
            ]
        );
    }

    /**
     * @param string $method
     * @param \Psr\Http\Message\UriInterface $url
     * @param array $parameters
     *
     * @return \Sta\TwitterPhpApiClient\Response
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function request(
        string $method, UriInterface $url, array $parameters, array $cacheOptions = [], ?BearerToken $bearerToken = null
    ): Response {
        $parameters[RequestOptions::HEADERS] = isset($parameters[RequestOptions::HEADERS]) ? $parameters[RequestOptions::HEADERS] : [];
        $bearerToken                         = $bearerToken ?: $this->defaultBearerToken;
        if ($bearerToken && !isset($parameters[RequestOptions::HEADERS]['Authorization'])) {
            $parameters[RequestOptions::HEADERS]['Authorization'] = $bearerToken;
        }

        $method    = strtoupper($method);
        $cacheItem = null;
        if ($this->cachePoll) {
            if ($method == 'GET' || $this->_getOpt($cacheOptions, self::CACHE_OPT_FORCE_USAGE)) {
                $cacheKey  = md5($method . $url . json_encode($parameters));
                $cacheItem = $this->cachePoll->getItem($cacheKey);
                if ($cacheItem->isHit()) {
                    return $this->cachePoll->getItem($cacheKey)->get();
                }
            }
        }

        $httpResponse = $this->httpClient->request($method, $url, $parameters);

        $response = new Response($httpResponse);

        if ($cacheItem) {
            if (!$response->hasError()) {
                $cacheItem->set($response);

                $expiresAfter = $this->_getOpt($cacheOptions, self::CACHE_OPT_EXPIRES_AFTER, '___NOT_DEFINED__');
                if ($expiresAfter !== '___NOT_DEFINED__') {
                    $cacheItem->expiresAfter($expiresAfter);
                }

                $this->cachePoll->save($cacheItem);
            }
        }

        return $response;
    }

    private function _getOpt(array $options, $key, $default = null)
    {
        if (array_key_exists($key, $options)) {
            return $options[$key];
        }

        return $default;
    }

    /**
     * @param string $userIdOrScreenName
     * @param bool $includeEntities
     *
     * @return \Sta\TwitterPhpApiClient\Response
     * @see https://developer.twitter.com/en/docs/accounts-and-users/follow-search-get-users/api-reference/get-users-show
     */
    public function getUsersShow(
        string $userIdOrScreenName, bool $includeEntities = false, ?BearerToken $bearerToken = null
    ): Response {
        $query = [
            'include_entities' => $includeEntities,
        ];

        if (preg_match('/\D/', $userIdOrScreenName)) {
            // Its important to always use the same screen name to improve cache usage (when cache is enabled).
            $userIdOrScreenName = strtolower($userIdOrScreenName);

            $query['screen_name'] = $userIdOrScreenName;
        } else {
            $query['user_id'] = $userIdOrScreenName;
        }

        return $this->request(
            'GET',
            new Uri(self::HOST_API . self::VERSION_API . '/users/show.json'),
            [
                RequestOptions::QUERY => $query,
            ]
        );
    }

    public function getStatusesUserTimeline(array $parameters, ?BearerToken $bearerToken = null): Response
    {
        return $this->request(
            'GET',
            new Uri(self::HOST_API . self::VERSION_API . '/statuses/user_timeline.json'),
            [
                RequestOptions::QUERY => $parameters,
            ]
        );
    }
}
