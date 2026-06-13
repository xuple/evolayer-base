<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->env = sys_get_temp_dir().'/evo-profile-'.uniqid().'.env';
});

afterEach(function () {
    File::delete($this->env);
});

test('lean profile turns every example flag off and leaves other env lines alone', function () {
    file_put_contents($this->env, "APP_NAME=Test\nEVOLAYER_BASE_EXAMPLE_THREAD_STUDIO=true\n");

    $this->artisan('evolayer:profile', ['profile' => 'lean', '--path' => $this->env])->assertSuccessful();

    $contents = file_get_contents($this->env);
    expect($contents)->toContain('EVOLAYER_BASE_EXAMPLE_THREAD_STUDIO=false')
        ->and($contents)->toContain('EVOLAYER_BASE_EXAMPLE_PRD_STUDIO=false') // appended when absent
        ->and($contents)->toContain('APP_NAME=Test');                        // untouched
});

test('demo profile turns every example flag on', function () {
    file_put_contents($this->env, "EVOLAYER_BASE_EXAMPLE_THREAD_STUDIO=false\n");

    $this->artisan('evolayer:profile', ['profile' => 'demo', '--path' => $this->env])->assertSuccessful();

    expect(file_get_contents($this->env))->toContain('EVOLAYER_BASE_EXAMPLE_THREAD_STUDIO=true');
});

test('an unknown profile fails', function () {
    file_put_contents($this->env, "X=1\n");

    $this->artisan('evolayer:profile', ['profile' => 'wat', '--path' => $this->env])->assertFailed();
});

test('a missing env file fails', function () {
    $this->artisan('evolayer:profile', ['profile' => 'lean', '--path' => '/no/such/path/.env'])->assertFailed();
});
