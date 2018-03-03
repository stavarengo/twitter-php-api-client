<?php
/**
 * twitter-php-api-client Project ${PROJECT_URL}
 *
 * @link      ${GITHUB_URL} Source code
 */

namespace Sta\TwitterPhpApiClient\Entity;

/**
 * Class BearerToken
 *
 * @method string getTokenType()
 * @method string getAccessToken()
 */
class BearerToken extends BaseEntity
{
    public function __toString()
    {
        return sprintf('%s %s', $this->getTokenType(), $this->getAccessToken());
    }
}
