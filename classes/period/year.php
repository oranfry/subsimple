<?php

namespace subsimple\period;

class year extends \subsimple\Period
{
    public $id = 'y';
    public $navlabel = 'Year';
    public $step = '1 year';
    public $graphdiv = '1 month';

    public function label($from)
    {
        return date('Y', strtotime($from));
    }

    public function rawstart($date)
    {
        return date('Y-01-01', strtotime($date));
    }
}
