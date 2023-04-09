<?php

namespace subsimple\period;

class financialyear extends \subsimple\Period
{
    public $graphdiv = '1 month';
    public $id = 'fy';
    public $navlabel = 'Financial Year';
    public $step = '1 year';

    function label($from)
    {
        return (date('Y', strtotime($from)) +1) . " Financial Year";
    }

    function rawstart($date)
    {
        return date('Y-04-01', strtotime(date_shift($date, '-3 month')));
    }
}
