<?php

namespace subsimple\period;

class gst extends \subsimple\Period
{
    public $id = 'gst';
    public $navlabel = 'GST Period';
    public $step = '2 month';

    public function label($from)
    {
        $y1 = date('Y', strtotime($from));
        $y2 = date('Y', strtotime(date_shift($from, '+1 month')));

        $m1 = date('M', strtotime($from));
        $m2 = date('M', strtotime(date_shift($from, '+1 month')));

        if ($y1 == $y2) {
            return "$m1 ~ $m2 $y1";
        }

        return "$m1 $y1 ~ $m2 $y2";
    }

    function rawstart($date)
    {
        $m = sprintf('%02d', (floor(substr($date, 5, 2) / 2) * 2 + 11) % 12 + 1);
        $y = date('Y', strtotime($date)) - ($m > date('m', strtotime($date)) ? 1 : 0);

        return "{$y}-{$m}-01";
    }
}
