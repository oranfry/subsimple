<?php

namespace subsimple\period;

class sundayfortnight extends \subsimple\Period
{
    public $id = 'sf';
    public $navlabel = 'Sunday Fortnight';
    public $step = '2 week';

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
        return date('Y-m-d', strtotime(date_shift($date, '-13 days')));
    }

    public function start($rawstart)
    {
        $time = strtotime(static::ff($rawstart, 'Sun'));
        $fortnight_in_s = 1209600;
        $week_in_s = 604800;
        $x = $time / $fortnight_in_s;

        if ($x - floor($x) >= 0.5) {
            $time += $week_in_s;
        }

        return date('Y-m-d', $time);
    }

    protected static function ff($date, $day = 'Mon')
    {
        while (date('D', strtotime($date)) != $day) {
            $date = date_shift($date, '1 day');
        }

        return $date;
    }
}
