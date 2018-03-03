<?php
/**
 * twitter-php-api-client Project ${PROJECT_URL}
 *
 * @link      ${GITHUB_URL} Source code
 */

namespace Sta\TwitterPhpApiClient\Entity;

class Tweets extends BaseEntity
{
    /**
     * @return \Sta\TwitterPhpApiClient\Entity\Tweet[]
     */
    public function getTweets(): array
    {
        return array_map(
            function (array $item) {
                return new Tweet($item);
            },
            $this->data
        );
    }
}
