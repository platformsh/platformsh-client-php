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

        $originalMessage = $e->getMessage();
        $message = $originalMessage;

        $responseInfoProperties = [
          'message',
          'detail',
          'error',
          'error_description',
        ];

        try {
            $response->getBody()->seek(0);
            $json = $response->json();
            foreach ($responseInfoProperties as $property) {
                if (!empty($json[$property])) {
                    $message .= " [$property] " . implode('; ', (array) $json[$property]);
                }
            }
        } catch (ParseException $parseException) {
            // Occasionally the response body may not be JSON.
            $response->getBody()->seek(0);
            $body = $response->getBody()->getContents();
            if ($body) {
                $message .= ' [extra] Non-JSON response body';
                $message .= ' [body] ' . $body;
            }
            else {
                $message .= ' [extra] Empty response body';
            }
        }

        // Re-create the exception to alter the message.
        if ($message !== $originalMessage) {
            $className = get_class($e);
            $e = new $className($message, $e->getRequest(), $e->getResponse());
        }

        return $e;
    }
}
