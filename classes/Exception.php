<?php

namespace subsimple;

class Exception extends \Exception
{
    protected ?string $public_message = null;

    public function publicMessage(?string $message = null)
    {
        if (func_num_args()) {
            $this->public_message = $message;

            return $this;
        }

        return $this->public_message;
    }
}
