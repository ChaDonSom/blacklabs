<?php

it('doesn\'t throw an error', function () {
    $this->artisan('show-site-branches')->assertExitCode(0);
});
