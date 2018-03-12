<?php
/**
 * twitter-php-api-client Project ${PROJECT_URL}
 *
 * @link      ${GITHUB_URL} Source code
 */

namespace Sta\TwitterPhpApiClient\Entity;

/**
 * Class Errors
 *
 * @method Error[] getErrors()
 */
class Errors extends BaseEntity
{
    public function getErrorByCode(int $code): ?Error
    {
        foreach ($this->getErrors() as $error) {
            if ($error->getCode() == $code) {
                return $error;
            }
        }

        return null;
    }

    public function hasErrorCode(int $code): bool
    {
        return !!$this->getErrorByCode($code);
    }
}
