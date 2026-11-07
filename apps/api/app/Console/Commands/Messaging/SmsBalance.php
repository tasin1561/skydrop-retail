<?php

namespace App\Console\Commands\Messaging;

use App\Services\Commerce\Notifier;
use App\Services\Commerce\Sms\SmsManager;
use App\Services\Messaging\MessagingSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SmsBalance extends Command
{
    protected $signature = 'retail:sms-balance {--alert : Email ALERT_EMAIL when a gateway is below the low-balance threshold}';

    protected $description = 'Show SMS gateway balances (BulkSMSBD, SSL Wireless, Alpha SMS) and alert when low';

    public function handle(SmsManager $sms, Notifier $notifier): int
    {
        $balances = $sms->balances();
        if ($balances === []) {
            $this->warn('No SMS gateway with credentials is configured (chain: '.implode(', ', $sms->chain()).').');

            return self::SUCCESS;
        }
        $low = (float) MessagingSettings::get('sms.low_balance_bdt', 200);
        $rows = [];
        foreach ($balances as $name => $b) {
            $state = $b === null ? 'unknown' : ($b < $low ? 'LOW' : 'ok');
            $rows[] = [$name, $b === null ? '—' : number_format($b, 2), $state];
            Cache::put('sms:balance:'.$name, ['balance' => $b, 'at' => now()->toIso8601String()], now()->addDays(2));
            if ($state === 'LOW' && $this->option('alert') && Cache::add('sms:low-alert:'.$name, 1, now()->addHours(20))) {
                $notifier->admin("Low SMS balance: {$name}", sprintf('The %s SMS balance is %.2f, below the %.2f alert threshold. Top up to keep OTP and order SMS flowing.', $name, $b, $low));
            }
        }
        $this->table(['Gateway', 'Balance', 'State'], $rows);

        return self::SUCCESS;
    }
}
