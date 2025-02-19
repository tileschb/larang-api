<?php

namespace App\Support\Database\Query\Grammars;

class MysqlGrammar extends \Illuminate\Database\Query\Grammars\MySqlGrammar
{
    public function getDateFormat(): string
    {
        return 'Y-m-d H:i:s.u';
    }
}
