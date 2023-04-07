<?php

namespace subsimple;

class Period extends Thing
{
    public $id;
    public $navlabel;
    public $step;

    public function rawstart($date)
    {
        return $date;
    }

    public function start($date)
    {
        return $date;
    }
}
