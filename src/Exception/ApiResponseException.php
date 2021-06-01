<?php

namespace Platformsh\Client\Exception;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

/**
 * Modifies Guzzle error messages to add more detail, if possible, based on the
 * response body.
 */
class ApiResponseException extends RequestException
{

    /**
     * Wraps a GuzzleException.
     *
     * @param \GuzzleHttp\Exception\GuzzleException $e
     *
     * @return GuzzleException
     */
    public static function wrapGuzzleException(GuzzleException $e)
    {
        return $e instanceof RequestException ? self::alterMessage($e) : $e;
    }

    /**
     * Recreates the exception if necessary to alter the message.
     *
     * @param RequestException $e
     *
     * @return RequestException
     */
    private static function alterMessage(RequestException $e)
    {
        if ($e->getResponse() !== null) {
            $details = self::getErrorDetails($e->getResponse());
            if (!empty($details)) {
                $class = \get_class($e);
                return new $class($e->getMessage() . $details, $e->getRequest(), $e->getResponse());
            }
        }

        return $e;
    }

    /**
     * Get more details from the response body, to add to error messages.
     *
     * @param ResponseInterface $response
     *
     * @return string
     */
    public static function getErrorDetails(ResponseInterface $response)
    {
        $responseInfoProperties = [
            // Platform.sh API errors.
            'message',
            'detail',
            // RESTful module errors.
            'title',
            'type',
            // OAuth2 errors.
            'error',
            'error_description',
        ];

        $details = '';

        $response->getBody()->seek(0);
        $contents = $response->getBody()->getContents();

        try {
            $json = \GuzzleHttp\json_decode($contents, true);
            foreach ($responseInfoProperties as $property) {
                if (!empty($json[$property])) {
                    $value = $json[$property];
                    $details .= " [$property] " . (is_scalar($value) ? $value : json_encode($value));
                }
            }
        } catch (\InvalidArgumentException $parseException) {
            // Occasionally the response body may not be JSON.
            if ($contents) {
                $details .= " [extra] Non-JSON response body";
                $details .= " [body] " . $contents;
            }
            else {
                $details .= " [extra] Empty response body";
            }
        }

        return $details;
    }
}
