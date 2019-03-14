<?php
/**
 * Project ${PROJECT_URL}
 *
 * @link      ${GITHUB_URL} Source code
 */

namespace Sta\TwitterPhpApiClient;


use Psr\Http\Message\ResponseInterface;
use Sta\TwitterPhpApiClient\Entity\BaseEntity;
use Sta\TwitterPhpApiClient\Entity\BearerToken;
use Sta\TwitterPhpApiClient\Entity\Errors;
use Sta\TwitterPhpApiClient\Entity\Tweets;
use Sta\TwitterPhpApiClient\Entity\User;
use Sta\TwitterPhpApiClient\Exception\EntityClassNotExpected;
use Sta\TwitterPhpApiClient\Exception\InvalidJsonString;

class Response implements \JsonSerializable
{
    /**
     * @var ResponseInterface
     */
    protected $httpResponse;
    /**
     * @var \Sta\TwitterPhpApiClient\Entity\BaseEntity
     */
    protected $entity = false;
    /**
     * @var string
     */
    protected $_bodyContents = false;

    /**
     * Response constructor.
     *
     * @param ResponseInterface $httpResponse
     * @param $entityClass
     */
    public function __construct(\Psr\Http\Message\ResponseInterface $httpResponse)
    {
        $this->httpResponse = $httpResponse;
    }

    public function getEntityError(): Errors
    {
        /** @var Errors $entity */
        $entity = $this->getEntityAndAssertItsClass(Errors::class);

        return $entity;
    }

    protected function getEntityAndAssertItsClass(string $expectedClass): BaseEntity
    {
        $entity = $this->getEntity($expectedClass);
        if (!($entity instanceof $expectedClass)) {
            throw new EntityClassNotExpected(
                sprintf(
                    'Could not convert data to "%s". It is more like this data represents an "%s" instead of "%s". Response body: "%s"',
                    $expectedClass,
                    get_class($entity),
                    $expectedClass,
                    $this->getBodyContents() ?? 'NULL'
                )
            );
        }

        return $entity;
    }

    /**
     * Get the Entity representing the response from Twitter.
     * Hands up! If the response is an error, this method will return null.
     *
     * @return BaseEntity
     *
     * @see \Sta\TwitterPhpApiClient\Response::getErrorEntity()
     */
    public function getEntity(?string $entityClassHint = null): BaseEntity
    {
        if (!$this->entity) {
            $this->entity = $this->_convertBodyResponseToEntity($entityClassHint);
        }

        return $this->entity ?: null;
    }

    protected function _convertBodyResponseToEntity(?string $entityClassHint = null): BaseEntity
    {
        $data = $this->_convertJsonStringToArray($this->getBodyContents());

        return BaseEntity::createEntityBasedOnWhatItLooksLike($data, $entityClassHint);
    }

    /**
     * Convert the response string we got from Twitter to an PHP object, so we can work with the Twitter data
     * using type hint from ours IDE and others stuffs.
     *
     * @param string $entityClass
     * @param string $bodyAsJsonString
     *
     * @return BaseEntity
     */
    protected function _convertJsonStringToArray(string $bodyAsJsonString): array
    {
        // Clear json_last_error()
        json_encode(null);

        $jsonDecodeResult = json_decode($bodyAsJsonString, true);
        if ($jsonDecodeResult === false || !is_array($jsonDecodeResult)) {
            throw new InvalidJsonString(
                sprintf(
                    'Could not interpret the string as an JSON. JSON error: "%s - %s". String received: %s',
                    json_last_error(),
                    json_last_error_msg(),
                    $bodyAsJsonString
                )
            );
        }

        return $jsonDecodeResult;
    }

    /**
     * Returns the current response body content, if there is a current response.
     * This method exists just to ensure we will not waste time reading the whole stream every time.
     *
     * @return string
     */
    protected function getBodyContents(): ?string
    {
        if ($this->_bodyContents === false) {
            $this->httpResponse->getBody()->rewind();
            $this->_bodyContents = $this->httpResponse->getBody()->getContents();
        }

        return $this->_bodyContents;
    }

    public function getEntityBearerToken(): BearerToken
    {
        /** @var BearerToken $entity */
        $entity = $this->getEntityAndAssertItsClass(BearerToken::class);

        return $entity;
    }

    public function getEntityTweets(): Tweets
    {
        /** @var \Sta\TwitterPhpApiClient\Entity\Tweets $entity */
        $entity = $this->getEntityAndAssertItsClass(Tweets::class);

        return $entity;
    }

    public function getEntityUser(): User
    {
        /** @var User $entity */
        $entity = $this->getEntityAndAssertItsClass(User::class);

        return $entity;
    }

    /**
     * The real response object that is being wrapped.
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function getHttpResponse(): \Psr\Http\Message\ResponseInterface
    {
        return $this->httpResponse;
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /**
     * Specify data which should be serialized to JSON
     *
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    public function jsonSerialize()
    {
        return [
            'httpResponse' => [
                'code' => $this->httpResponse->getStatusCode(),
                'body' => $this->getBodyContents(),
            ],
            'hasError' => $this->hasError(),
            'entity' => $this->getEntity(),
        ];
    }

    /**
     * Returns true if the response contains an error.
     *
     * @return bool
     */
    public function hasError()
    {
        $entity = $this->_convertBodyResponseToEntity();

        return !$entity || $entity instanceof Errors;
    }

    /**
     * Necessary because this object will be serialized when cached.
     *
     * @return array
     */
    public function __sleep()
    {
        $this->getEntity();

        return [
            'entity',
        ];
    }

}
