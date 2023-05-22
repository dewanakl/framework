<?php

namespace Test\Model;

use Core\Database\DB;
use Core\Model\Model;
use Core\Model\Query;
use Test\ApplicationTest as TestCase;

class DBTest extends TestCase
{
    public function testIsDB()
    {
        $model = DB::table('users');

        $this->assertTrue($model instanceof Model);
    }

    public function testDBMustBeReturnQuery()
    {
        $model = DB::table('users')->limit(5);

        $this->assertTrue($model instanceof Query);
    }
}
