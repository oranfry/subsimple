<?php
namespace period;

class month extends \Period
{
    public $id = 'm';
    public $navlabel = 'Month';
    public $step = '1 month';
    public $graphdiv = '1 week';
    public $graphdivff = true;

    public function label($from)
    {
        return date('F Y', strtotime($from));
    }

    public function rawstart($date)
    {
        return date('Y-m-01', strtotime($date));
    }
}
