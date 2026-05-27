<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The default test case class applied to all Pest tests in this project.
| Feature tests that touch the database should declare:
|   uses(RefreshDatabase::class);
| at the top of the file, which Pest will apply to every test in that file.
|
*/

pest()->extend(TestCase::class)->in('Feature', 'Unit', 'integration');
