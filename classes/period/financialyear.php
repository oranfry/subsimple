<?php

namespace subsimple\period;

class financialyear extends \subsimple\Period
{
    public function __construct()
    {
        $this->id = 'fy';
        $this->navlabel = 'Financial Year';
        $this->step = '1 year';
        $this->graphdiv = '1 month';
    }

    function label($from)
    {
        return (date('Y', strtotime($from)) +1) . " Financial Year";
    }

    function rawstart($date)
    {
        return date('Y-04-01', strtotime(date_shift($date, '-3 month')));
    }
}
