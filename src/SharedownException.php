<?php
/**
 * General exception for Sharedown project client library.
 *
 * @author Alexander Lokhman <alex.lokhman@gmail.com>
 * @link https://github.com/lokhman/sharedown-php
 *
 * Copyright (c) 2016 Alexander Lokhman <alex.lokhman@gmail.com>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
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
