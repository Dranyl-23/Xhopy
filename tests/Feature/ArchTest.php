<?php

arch('application does not leave debug calls in source')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r'])
    ->not->toBeUsed();

arch('strict security preset')
    ->preset()
    ->security();
