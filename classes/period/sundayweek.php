<?php
namespace period;

class sundayweek extends \Period
{
    public $id = 'sw';
    public $navlabel = 'Sunday Week';
    public $step = '1 week';

    public function label($from, $to)
    {
        $m1 = date('M', strtotime($from));
        $m2 = date('M', strtotime($to));

        $y1 = date('Y', strtotime($from));
        $y2 = date('Y', strtotime($to));

        return date('j' . ($m1 != $m2 ? ' M' : '') . ($y1 != $y2 ? ' Y' : ''), strtotime($from)) . " - " . date('j M Y', strtotime($to));
    }

    public function rawstart($date)
    {
        return date('Y-m-d', strtotime(date_shift($date, '-6 days')));
    }

    public function start($rawstart)
    {
        return ff($rawstart, 'Sun');
    }
}
