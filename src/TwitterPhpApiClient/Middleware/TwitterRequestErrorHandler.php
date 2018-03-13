<?php
/**
 * php-clap-api Project ${PROJECT_URL}
 *
 * @link      ${GITHUB_URL} Source code
 */

namespace Cely\TwitterClient\Middleware;

use Cely\Bombadil\LoggerHelper;
use Cely\TwitterClient\Middleware\Exception\ResponseStringIsNotJson;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Zend\Json\Json;

class TwitterRequestErrorHandler
{
    public const DO_NOT_CHECK_JSON_SYNTAX = '\Cely\TwitterClient\Middleware\TwitterRequestErrorHandler::DO_NOT_CHECK_JSON_SYNTAX';
    protected static $maxTriesPerError = [
        '_default' => 10,
        404 => 1,
    ];
    protected static $unexpectedExceptionsPatterns = [
        '/cURL error 18: transfer closed with/',
        '/cURL error 56: SSL read/',
        '/cURL error 28: Operation timed out after/',
        '/cURL error 35: error:/',
    ];
    /**
     * @var bool
     */
    private static $tryToAvoidRateLimit = false;
    /**
     * @var \Cely\Bombadil\LoggerHelper
     */
    private $logger;

    public function __construct(LoggerHelper $logger)
    {
        $this->logger = $logger;
    }

    public function __invoke()
    {
        return function (callable $handler) {
            return function (RequestInterface $request, array $options) use ($handler) {
                if (self::$tryToAvoidRateLimit) {
                    // Espera alguns milesimos para diminuir o problema de limite de requisição no Twitter
                    $microSeconds = ceil(mt_rand(1000000, 4000000) / mt_rand(2, 60));
                    $this->logger->debug(
                        $this,
                        [
                            'Sleeping %s seconds seconds to avoid Twitter request limit rate.',
                            round($microSeconds / 1000000),
                        ]
                    );
                    usleep($microSeconds);
                }

                return $this->_performRequest($handler, $request, $options, []);
            };
        };
    }

    private function _performRequest(
        callable $handler, RequestInterface $request, array $options, array $tries
    ) {
        /** @var \GuzzleHttp\Promise\PromiseInterface $promise */
        $promise = $handler($request, $options);

        return $promise->then(
            function (ResponseInterface $response) use ($request, $handler, $options, &$tries) {
                $statusCode = $response->getStatusCode();
                $errorCode  = null;
                $delay      = mt_rand($this->_secondsToMicroSeconds(15), $this->_secondsToMicroSeconds(30));
                $msg        = sprintf(
                    'Got a "%s-%s" error while performing a request to TWITTER.',
                    $statusCode,
                    $statusCode == 429 ? 'TOO MANY REQUESTS' : strtoupper($response->getReasonPhrase())
                );

                if ($statusCode > 399) {
                    $errorCode = $statusCode;

                    if ($statusCode == 429) {
                        // Twitter request rate limit reached
                        $delay = mt_rand($this->_minutesToMicroSeconds(1), $this->_minutesToMicroSeconds(3));
                    }
                } else if ($statusCode == 200) {
                    if (!isset($options[self::DO_NOT_CHECK_JSON_SYNTAX]) || !$options[self::DO_NOT_CHECK_JSON_SYNTAX]) {
                        $bodyContent = $response->getBody()->getContents();
                        $response->getBody()->rewind();
                        try {
                            Json::decode($bodyContent, Json::TYPE_ARRAY);
                        } catch (\Exception $e) {
                            throw new ResponseStringIsNotJson(
                                sprintf(
                                    'Could not convert this response to JSON. Request URL: "%s". Response Code: "%s". Response content: "%s".',
                                    $request->getUri(),
                                    $statusCode,
                                    $bodyContent
                                ),
                                $e->getCode(),
                                $e
                            );
                        }
                    }
                }

                return $this->_tryAgainIfItIsAllowed(
                    $handler,
                    $request,
                    $options,
                    $response,
                    $errorCode,
                    $tries,
                    $delay,
                    $msg
                );
            },
            function ($reason) use ($request, $handler, $options, &$tries) {
                if (!($reason instanceof RequestException)) {
                    throw $reason;
                }

                foreach (self::$unexpectedExceptionsPatterns as $pattern) {
                    if (preg_match($pattern, $reason->getMessage())) {
                        $errorCode = -1;
                        $delay     = mt_rand($this->_secondsToMicroSeconds(15), $this->_secondsToMicroSeconds(30));
                        $msg       = sprintf(
                            'Got an unexpected error while performing a request to TWITTER: "%s".',
                            $pattern
                        );

                        return $this->_tryAgainIfItIsAllowed(
                            $handler,
                            $request,
                            $options,
                            $reason,
                            $errorCode,
                            $tries,
                            $delay,
                            $msg
                        );
                    }
                }

                throw $reason;
            }
        );
    }

    private function _secondsToMicroSeconds(int $howManySeconds)
    {
        return 1000000 * $howManySeconds;
    }

    private function _minutesToMicroSeconds(int $howManyMinutes)
    {
        return $this->_secondsToMicroSeconds($howManyMinutes * 60);
    }

    private function _tryAgainIfItIsAllowed(
        callable $handler, RequestInterface $request, array $options, $response, ?int $errorCode, array $tries,
        int $delayInMicroSeconds, string $msg
    ) {
        if ($errorCode !== null) {
            $allowedTries   = $this->_getAllowedMaxTriesForThisErrorCode($errorCode);
            $triesPerformed = isset($tries[$errorCode]) ? $tries[$errorCode] : 0;

            if ($triesPerformed < $allowedTries) {
                $tries = $this->_increaseTriesCount($errorCode, $tries);

                return $this->_tryAgain($request, $handler, $options, $tries, $delayInMicroSeconds, $msg);
            }
        }

        if ($response instanceof ResponseInterface) {
            return $response;
        }

        throw $response;
    }

    private function _getAllowedMaxTriesForThisErrorCode(int $errorCode): int
    {
        if (array_key_exists($errorCode, self::$maxTriesPerError)) {
            return self::$maxTriesPerError[$errorCode];
        }

        return self::$maxTriesPerError['_default'];
    }

    private function _increaseTriesCount(int $errorCode, array $tries): array
    {
        if (!array_key_exists($errorCode, $tries)) {
            $tries[$errorCode] = 0;
        }
        $tries[$errorCode]++;

        return $tries;
    }

    private function _tryAgain(
        RequestInterface $request, callable $handler, array $options, array $tries, int $delayInMicroSeconds,
        string $msg
    ) {
        $this->logger->warn(
            $this,
            [
                'Sleeping %s seconds before tying again. %s.',
                round($delayInMicroSeconds / 1000000),
                $msg,
            ]
        );

        usleep($delayInMicroSeconds);

        return $this->_performRequest($handler, $request, $options, $tries);
    }
}
