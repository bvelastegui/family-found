<?php

namespace App\Actions\Fund;

use App\Models\FundSetting;
use App\Models\FundTransaction;
use App\Models\User;
use App\Notifications\FundActivityNotification;
use App\Notifications\FundActivityPushNotification;

class NotifyFundTransaction
{
    public function registered(FundTransaction $transaction): void
    {
        $treasurerId = FundSetting::current()->treasurer_id;
        if ($treasurerId === null) {
            return;
        }

        $this->send(User::query()->findOrFail($treasurerId), 'Comprobante por conciliar',
            "{$transaction->user->name} registró un comprobante para revisar.", $transaction);
    }

    public function approved(FundTransaction $transaction): void
    {
        $this->send($transaction->user, 'Comprobante aprobado',
            'Tu transferencia fue aprobada y contabilizada.', $transaction);
    }

    public function rejected(FundTransaction $transaction): void
    {
        $this->send($transaction->user, 'Comprobante rechazado',
            'Tu transferencia fue rechazada. Revisa el motivo en el detalle.', $transaction);
    }

    private function send(User $recipient, string $title, string $body, FundTransaction $transaction): void
    {
        $url = route('fund.transactions.show', $transaction, false);
        $recipient->notify(new FundActivityNotification($title, $body, $url));

        if (filled(config('webpush.vapid.public_key')) && $recipient->pushSubscriptions()->exists()) {
            $recipient->notify(new FundActivityPushNotification($title, $body, $url));
        }
    }
}
