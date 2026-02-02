<?php

it('doesn\'t throw an error', function () {
    fakeForgeApi('release/v1.0.0/123');
    $this->artisan('show-site-branches')->assertExitCode(0);
});
