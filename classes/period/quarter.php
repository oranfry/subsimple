<?php

namespace subsimple\period;

class quarter extends \subsimple\Period
{
    public $id = 'q';
    public $navlabel = 'Quarter';
    public $step = '3 month';
    public $graphdiv = '1 month';

    public function label($from)
    {
        $q = $this->qnum($from);
        $y = date('Y', strtotime($from));

        return "Q{$q} {$y}";
    }

    private function qnum($date)
    {
        return ceil(intval(date('n', strtotime($date))) / 3);
    }

    public function rawstart($date)
    {
        $m = str_pad((string) ($this->qnum($date) * 3 - 2), 2, '0', STR_PAD_LEFT);

        return date("Y-{$m}-01", strtotime($date));
    }
}
