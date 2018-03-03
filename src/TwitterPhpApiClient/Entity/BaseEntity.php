<?php
/**
 * Project ${PROJECT_URL}
 *
 * @link      ${GITHUB_URL} Source code
 */

namespace Sta\TwitterPhpApiClient\Entity;

use GuzzleHttp\Psr7\Uri;
use Sta\TwitterPhpApiClient\TwitterClient;

class BaseEntity
{
    /**
     * @var \ReflectionClass[]
     */
    protected static $classReflection = [];
    /**
     * @var array
     */
    protected $data = [];
    /**
     * @var array
     */
    protected $cache = [];

    public static function isArrayOfEntities(array $data)
    {
        $isAssociativeArray = array_keys($data) !== range(0, count($data) - 1);

        return array_reduce(
            $data,
            function (bool $previousItemWasAEntity, $item) {
                return $previousItemWasAEntity && is_array($item);
            },
            !$isAssociativeArray
        );
    }

    public static function createEntityBasedOnWhatItLooksLike(array $data): BaseEntity
    {
        $class = null;
        if (isset($data['errors'])) {
            $class = Errors::class;
        } else if (count($data) == 3 && isset($data['code']) && isset($data['message']) && isset($data['label'])) {
            $class = Error::class;
        } else if (count($data) == 2 && isset($data['token_type']) && $data['token_type'] == 'bearer') {
            $class = BearerToken::class;
        } else if (isset($data['id']) && isset($data['name']) && isset($data['description'])) {
            $class = User::class;
        } else if (isset($data['entities']['user_mentions']) && array_key_exists('in_reply_to_status_id', $data)
            && array_key_exists('retweet_count', $data)
        ) {
            $class = Tweet::class;
        } else {
            if (self::isArrayOfEntities($data)) {
                $firstEntity = self::createEntityBasedOnWhatItLooksLike(reset($data));
                if ($firstEntity instanceof Tweet) {
                    $class = Tweets::class;
                }
            }
        }

        if (!$class) {
//            throw new UnableToDetectReponseEntityClass();
            $class = BaseEntity::class;
        }

        return new $class($data);
    }

    /**
     * BaseEntity constructor.
     *
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * @return array
     */
    public function getRawData()
    {
        return $this->data;
    }

    public function __call($methodName, $arguments)
    {
        $attributeName = preg_replace('/^get/', '', $methodName);

        return $this->get($attributeName);
    }

    /**
     * Returns the value of an attribute.
     *
     * @param $name
     *      The name of the attribute, write in either camel case, or snake case (eg: content_geo_statistic or
     *      ContentGeoStatistic).
     *
     * @return mixed
     *
     * @throws \Sta\TwitterPhpApiClient\Entity\Exception\AllObjectAttributesOfAnEntityMustAlsoBeAnInstanceOfAnEntity
     * @throws \Sta\TwitterPhpApiClient\Entity\Exception\AttributeNotFound
     */
    public function get(string $name, bool $returnRawData = false)
    {
        $result                    = null;
        $nameAsSnakeCaseFirstLower = $this->_camelCaseToSnakeCase($name, 'lower');
        $cacheKey                  = sprintf('%s:%s', get_class($this), $nameAsSnakeCaseFirstLower);

        if (array_key_exists($cacheKey, $this->cache)) {
            $result = $this->cache[$cacheKey];
        } else {
            $rawData = null;
            if (array_key_exists($nameAsSnakeCaseFirstLower, $this->data)) {
                $rawData = $this->data[$nameAsSnakeCaseFirstLower];
            }

            $result = $rawData;

            if ($returnRawData) {
                return $rawData;
            }

            if (is_array($rawData)) {
                if (self::isArrayOfEntities($rawData)) {
                    $result = array_map(
                        function (array $item) {
                            return self::createEntityBasedOnWhatItLooksLike($item);
                        },
                        $rawData
                    );
                } else {
                    $result = self::createEntityBasedOnWhatItLooksLike($rawData);
                }
            } else if (is_string($rawData)) {
                if (strlen($rawData) == 30
                    && $datetime = \DateTime::createFromFormat(TwitterClient::DEFAULT_TWITTER_DATETIME_FORMAT, $rawData)
                ) {
                    $result = $datetime;
                } else if (preg_match('!^https?://.+!', $rawData)) {
                    $result = new Uri($rawData);
                }
            }

            $this->cache[$cacheKey] = $result;
        }

        return $result;
    }

    /**
     * @param string $string
     *
     * @param string $first
     *      Use 'lower' or 'upper'. Anything different of these values will be ignored.
     *
     * @return string
     */
    private function _camelCaseToSnakeCase($string, $first = 'lower')
    {
        $result = preg_replace_callback(
            '/([a-z])([A-Z])(?![A-Z])/',
            function ($matches) {
                return $matches[1] . '_' . mb_strtolower($matches[2], 'UTF-8');
            },
            $string
        );

        switch ($first) {
            case 'lower':
                $result = lcfirst($result);
                break;
            case 'upper':
                $result = ucfirst($result);
                break;
        }

        return $result;
    }

    /**
     * @param string $string
     *
     * @param string $first
     *      Use 'lower' or 'upper'. Anything different of these values will be ignored.
     *
     * @return string
     */
    private function _snakeCaseToCamelCase($string, $first = 'upper')
    {
        $result = preg_replace_callback(
            '/_(.)/',
            function ($matches) {
                return mb_strtoupper($matches[1], 'UTF-8');
            },
            $string
        );

        switch ($first) {
            case 'lower':
                $result = lcfirst($result);
                break;
            case 'upper':
                $result = ucfirst($result);
                break;
        }

        return $result;
    }
}
