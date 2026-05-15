<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$users = App\Models\User::query()
    ->whereHas('provider')
    ->with('provider:id,user_id,is_active,is_verified')
    ->limit(15)
    ->get(['id', 'name', 'email', 'email_verified_at']);

foreach ($users as $u) {
    echo sprintf(
        "%s | %s | verified=%s | active=%s\n",
        $u->email,
        $u->name,
        $u->email_verified_at ? 'yes' : 'no',
        $u->provider?->is_active ? 'yes' : 'no'
    );
}
