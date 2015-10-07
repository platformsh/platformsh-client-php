<?php

namespace Platformsh\Client\Exception;

use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Message\RequestInterface;
use GuzzleHttp\Message\ResponseInterface;
use GuzzleHttp\Exception\ParseException;

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
      \Exception $previous = null
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

        try {
            $response->getBody()->seek(0);
            $json = $response->json();
            foreach ($responseInfoProperties as $property) {
                if (!empty($json[$property])) {
                    $details .= " \n [$property] " . implode('; ', (array) $json[$property]);
                }
            }
        } catch (ParseException $parseException) {
            // Occasionally the response body may not be JSON.
            $response->getBody()->seek(0);
            $body = $response->getBody()->getContents();
            if ($body) {
                $details .= " \n [extra] Non-JSON response body";
                $details .= " \n [body] " . $body;
            }
            else {
                $details .= " \n [extra] Empty response body";
            }
        }

        return $details;
    }
}
