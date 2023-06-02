<?php

it('doesn\'t throw an error', function () {
    $this->artisan('devops:show-site-branches')->assertExitCode(0);
});
