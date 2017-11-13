<?php

namespace Platformsh\Client\Exception;

use GuzzleHttp\Exception\BadResponseException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class ApiResponseException extends BadResponseException
{

    /**
     * @inheritdoc
     *
     * Modifies Guzzle error messages to add more detail, based on the response
     * body.
     */
    public static function create(
      RequestInterface $request,
      ResponseInterface $response = null,
      \Exception $previous = null,
      array $ctx = []
    ) {
        $e = parent::create($request, $response, $previous);
        if ($response === null) {
            return $e;
        }

        // Re-create the exception to alter the message.
        $details = self::getErrorDetails($response);
        if (!empty($details)) {
            $className = get_class($e);
            $e = new $className($e->getMessage() . $details, $e->getRequest(), $e->getResponse());
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
