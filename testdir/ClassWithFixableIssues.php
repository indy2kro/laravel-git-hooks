<?php

namespace Test;

class ClassWithFixableIssues
{
    public function test()
    {
        if ($a = 1 == 1) {
            echo "test";
        }
    }
}
