<?php

namespace Platformsh\Client\Exception;

use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Message\RequestInterface;
use GuzzleHttp\Message\ResponseInterface;

class ApiResponseException extends BadResponseException
{

    /**
     * @inheritdoc
     */
    public static function create(
      RequestInterface $request,
      ResponseInterface $response = null,
      \Exception $previous = null
    ) {
        $e = parent::create($request, $response, $previous);

        // Modify the error message to add more detail.
        if (($response = $e->getResponse()) && ($json = $response->json())) {
            $append = '';
            if (isset($json['message'])) {
                $append .= ' [message] ' . implode('; ', (array) $json['message']);
            }
            if (isset($json['detail'])) {
                $append .= ' [detail] ' . implode('; ', (array) $json['detail']);
            }
            if ($append) {
                $message = $e->getMessage() . $append;
                $className = get_class($e);
                $e = new $className($message, $e->getRequest(), $e->getResponse());
            }
        }

        return $e;
    }
}
