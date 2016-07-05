<?php

/**
 * General exception for Sharedown project client library.
 *
 * @author Alexander Lokhman <alex.lokhman@gmail.com>
 * @link https://github.com/lokhman/sharedown-php
 */
class SharedownException extends Exception {

    protected $statusCode;

    /**
     * Sharedown general exception constructor.
     *
     * @param type $message         [optional] <p>The exception message to throw.</p>
     * @param type $code            [optional] <p>The exception code.</p>
     * @param Exception $previous   [optional] <p>The previous exception used for the exception chaining.</p>
     */
    public function __construct($message = '', $code = 0, Exception $previous = null) {
        if (strpos($message, 'HTTP/') === 0) {
            $message = substr($message, strpos($message, ' ') + 1);
            if (is_numeric($statusCode = explode(' ', $message)[0])) {
                $this->statusCode = (int) $statusCode;
            }
        }

        parent::__construct($message, $code, $previous);
    }

    /**
     * Returns request status code if exists.
     *
     * @return int|null
     */
    public function getStatusCode() {
        return $this->statusCode;
    }

}
