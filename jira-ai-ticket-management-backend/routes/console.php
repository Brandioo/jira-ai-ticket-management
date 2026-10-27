<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('education:about', function () {
    $this->info('Jira AI Ticket Management education project');
});
