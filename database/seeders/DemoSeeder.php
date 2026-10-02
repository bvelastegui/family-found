<?php

namespace Database\Seeders;

use App\Actions\Fund\FundAdministration;
use App\Actions\Fund\FundContributions;
use App\Actions\Fund\FundLoans;
use App\Actions\Fund\FundTransactions;
use App\Actions\Fund\InstallFund;
use App\Actions\Fund\RecordFundEvent;
use App\Enums\TransactionStatus;
use App\Models\Bank;
use App\Models\Evidence;
use App\Models\FundInvitation;
use App\Models\FundSetting;
use App\Models\LoanInstallment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;
use Throwable;

class DemoSeeder extends Seeder
{
    public const string PASSWORD = 'DemoFamilia2026!';

    public function run(
        InstallFund $install,
        FundAdministration $administration,
        FundContributions $contributions,
        FundTransactions $transactions,
        FundLoans $loans,
        RecordFundEvent $events,
    ): void {
        $fund = FundSetting::query()->find(1);
        if ($fund !== null) {
            if (User::query()->whereKey($fund->administrator_id)->where('email', 'admin.demo@example.test')->exists()
                && DB::table('operation_events')->where('event', 'demo.seeded')->where('actor_id', $fund->administrator_id)->exists()) {
                if ($fund->auditor_id === null) {
                    $auditor = User::query()->where('email', 'auditor.demo@example.test')->first()
                        ?? $this->createMember('Alicia Demo', 'auditor', Hash::make(self::PASSWORD));
                    $administration->auditor(User::query()->findOrFail($fund->administrator_id), (string) Str::uuid(), $auditor->id);
                }

                return;
            }

            throw new LogicException('DemoSeeder necesita una base de datos de demostración sin un fondo instalado.');
        }

        $today = CarbonImmutable::now('America/Guayaquil');
        $firstMonth = $today->startOfMonth()->subMonths(5);
        $originalEvidenceIds = Evidence::query()->pluck('id')->all();
        $createdPaths = [];

        try {
            DB::transaction(function () use ($install, $administration, $contributions, $transactions, $loans, $events, $today, $firstMonth, $originalEvidenceIds, &$createdPaths): void {
                try {
                    $this->call(BankSeeder::class);
                    $banks = Bank::query()->where('active', true)->orderBy('id')->limit(3)->get();
                    $members = $this->at($firstMonth->setTime(9, 0), function () use ($install, $administration, $today): array {
                        $fund = $install->handle('Adriana Demo', 'admin.demo@example.test', self::PASSWORD);
                        $administrator = User::query()->findOrFail($fund->administrator_id);
                        $administrator->forceFill(['email_verified_at' => now()])->save();
                        $password = Hash::make(self::PASSWORD);
                        $treasurer = $this->createMember('Tomás Demo', 'tesorero', $password);
                        $administration->treasurer($administrator, (string) Str::uuid(), $treasurer->id);
                        $members = [$administrator, $treasurer];
                        $people = [
                            'ana' => 'Ana Torres', 'bruno' => 'Bruno Martínez', 'carla' => 'Carla Mendoza',
                            'diego' => 'Diego López', 'elena' => 'Elena Castillo', 'fabian' => 'Fabián Romero',
                            'gabriela' => 'Gabriela Silva', 'hugo' => 'Hugo Ramírez', 'ines' => 'Inés Morales',
                            'javier' => 'Javier Ortiz', 'karina' => 'Karina Pérez', 'luis' => 'Luis Andrade',
                            'mariana' => 'Mariana Salazar', 'nicolas' => 'Nicolás Herrera', 'olivia' => 'Olivia Rojas',
                            'pablo' => 'Pablo García', 'auditor' => 'Alicia Demo', 'rosa' => 'Rosa Vargas',
                        ];
                        foreach ($people as $email => $name) {
                            $member = $this->createMember($name, $email, $password);
                            if ($email === 'rosa') {
                                $joinedAt = $today->day >= 6 ? $today->startOfDay() : $today->startOfMonth()->subDays(2);
                                $member->forceFill(['created_at' => $joinedAt->utc(), 'updated_at' => $joinedAt->utc(), 'email_verified_at' => $joinedAt->utc()])->save();
                            }
                            $members[] = $member;
                        }
                        $administration->auditor($administrator, (string) Str::uuid(), $members[18]->id);

                        return $members;
                    });
                    $treasurer = $members[1];
                    $periodIds = $this->at($firstMonth->setTime(10, 0), function () use ($contributions, $treasurer, $firstMonth): array {
                        $periodIds = [];
                        for ($offset = 0; $offset < 8; $offset++) {
                            $periodIds[] = $contributions->setPeriod($treasurer, (string) Str::uuid(), $firstMonth->addMonths($offset)->format('Y-m'), '25.00');
                        }

                        return $periodIds;
                    });

                    foreach ([19, 18, 17, 16, 15, 11] as $offset => $paidCount) {
                        $month = $firstMonth->addMonths($offset);
                        for ($index = 0; $index < $paidCount; $index++) {
                            $date = $month->addDays(2 + $index % 3)->setTime(10, 0)->min($today);
                            $this->payment($transactions, $members[$index], $treasurer, $banks[$index % 3]->id,
                                'DEMO-APORTE-'.$month->format('Ym').'-'.$index, '25.00', [$periodIds[$offset]], [], $date);
                        }
                    }
                    foreach ([11, 12, 13, 14] as $index) {
                        $this->payment($transactions, $members[$index], $treasurer, $banks[$index % 3]->id,
                            'DEMO-APORTE-ACTUAL-'.$index, '25.00', [$periodIds[5]], [], $today,
                            $index === 14 ? TransactionStatus::Rejected : TransactionStatus::Pending);
                    }

                    $this->seedLoans($loans, $transactions, $members, $banks[0]->id, $today);
                    $this->seedInvitations($treasurer, $today);
                    DB::table('notifications')->where('created_at', '<', $today->startOfMonth()->utc())->update(['read_at' => $today->utc()]);
                    $events->handle($members[0], 'demo.seeded', 'fund', 1, ['first_month' => $firstMonth->format('Y-m')]);
                } catch (Throwable $exception) {
                    $createdPaths = Evidence::query()->whereNotIn('id', $originalEvidenceIds)->pluck('path')->all();

                    throw $exception;
                }
            });
        } catch (Throwable $exception) {
            Storage::disk('fund')->delete($createdPaths);

            throw $exception;
        }
    }

    private function createMember(string $name, string $email, string $password): User
    {
        $user = User::query()->create(['name' => $name, 'email' => $email.'.demo@example.test', 'password' => $password]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    /** @param list<User> $members */
    private function seedLoans(FundLoans $loans, FundTransactions $transactions, array $members, int $bankId, CarbonImmutable $today): void
    {
        $treasurer = $members[1];
        foreach ([
            ['member' => 4, 'amount' => '200.00', 'term' => 3, 'months_ago' => 4, 'paid' => 3],
            ['member' => 2, 'amount' => '500.00', 'term' => 6, 'months_ago' => 3, 'paid' => 2],
            ['member' => 3, 'amount' => '300.00', 'term' => 4, 'months_ago' => 2, 'paid' => 1],
        ] as $scenario) {
            $member = $members[$scenario['member']];
            $date = $today->startOfMonth()->subMonths($scenario['months_ago'])->addDays(9)->setTime(11, 0);
            $loanId = $this->at($date, function () use ($loans, $member, $treasurer, $scenario, $bankId, $date): int {
                $loanId = $loans->reserve($treasurer, (string) Str::uuid(), [
                    'user_id' => $member->id, 'amount' => $scenario['amount'], 'monthly_rate' => '2.00', 'term_months' => $scenario['term'],
                ]);
                $reference = 'DEMO-DESEMBOLSO-'.$member->id;

                return $this->withReceipt($reference, $scenario['amount'], $date, fn (UploadedFile $file): int => $loans->disburse(
                    $treasurer, (string) Str::uuid(), $loanId,
                    ['bank_id' => $bankId, 'reference' => $reference, 'transaction_date' => $date->toDateString(), 'amount' => $scenario['amount']], $file,
                ));
            });
            $installments = LoanInstallment::query()->where('loan_id', $loanId)->orderBy('number')->get();
            foreach ($installments->take($scenario['paid']) as $installment) {
                $paymentDate = CarbonImmutable::parse($installment->due_on->format('Y-m-d'), 'America/Guayaquil')->setTime(12, 0)->min($today);
                $this->payment($transactions, $member, $treasurer, $bankId, 'DEMO-CUOTA-'.$installment->id,
                    $this->money($installment->capital_cents + $installment->interest_cents), [], [$installment->id], $paymentDate);
            }
            if ($scenario['member'] === 2) {
                $installment = $installments[$scenario['paid']];
                $this->payment($transactions, $member, $treasurer, $bankId, 'DEMO-CUOTA-PENDIENTE-'.$installment->id,
                    $this->money($installment->capital_cents + $installment->interest_cents), [], [$installment->id], $today, TransactionStatus::Pending);
            }
        }
        foreach ([5 => '150.00', 6 => '100.00', 7 => '75.00'] as $index => $amount) {
            $loanId = $loans->reserve($treasurer, (string) Str::uuid(), ['user_id' => $members[$index]->id, 'amount' => $amount, 'monthly_rate' => '2.00', 'term_months' => 6]);
            if ($index === 7) {
                $loans->cancel($treasurer, (string) Str::uuid(), $loanId, 'El participante decidió posponer la solicitud.');
            }
        }
    }

    private function seedInvitations(User $treasurer, CarbonImmutable $today): void
    {
        foreach ([
            ['email' => 'invitada.demo@example.test', 'expires_at' => $today->addDays(7)],
            ['email' => 'expirada.demo@example.test', 'expires_at' => $today->subDay(), 'created_at' => $today->subDays(8)],
            ['email' => 'cancelada.demo@example.test', 'expires_at' => $today->addDays(7), 'cancelled_at' => $today],
        ] as $invitation) {
            FundInvitation::factory()->create(['invited_by_id' => $treasurer->id, ...$invitation]);
        }
    }

    /**
     * @param  list<int>  $periodIds
     * @param  list<int>  $installmentIds
     */
    private function payment(FundTransactions $transactions, User $member, User $treasurer, int $bankId, string $reference, string $amount, array $periodIds, array $installmentIds, CarbonImmutable $date, TransactionStatus $status = TransactionStatus::Approved): int
    {
        return $this->at($date, function () use ($transactions, $member, $treasurer, $bankId, $reference, $amount, $periodIds, $installmentIds, $date, $status): int {
            $id = $this->withReceipt($reference, $amount, $date, fn (UploadedFile $file): int => $transactions->register($member, (string) Str::uuid(), [
                'bank_id' => $bankId, 'reference' => $reference, 'transaction_date' => $date->toDateString(),
                'amount' => $amount, 'period_ids' => $periodIds, 'installment_ids' => $installmentIds,
            ], $file));
            if ($status === TransactionStatus::Approved) {
                $transactions->approve($treasurer, (string) Str::uuid(), $id);
            } elseif ($status === TransactionStatus::Rejected) {
                $transactions->reject($treasurer, (string) Str::uuid(), $id, 'La referencia del comprobante no coincide con el depósito recibido.');
            }

            return $id;
        });
    }

    private function money(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    private function at(CarbonImmutable $date, Closure $operation): mixed
    {
        $previous = Date::getTestNow();
        $previousImmutable = CarbonImmutable::getTestNow();
        Date::setTestNow($date);
        CarbonImmutable::setTestNow($date);

        try {
            return $operation();
        } finally {
            Date::setTestNow($previous);
            CarbonImmutable::setTestNow($previousImmutable);
        }
    }

    /** @param Closure(UploadedFile): int $operation */
    private function withReceipt(string $reference, string $amount, CarbonImmutable $date, Closure $operation): int
    {
        $file = tmpfile();
        if ($file === false) {
            throw new RuntimeException('No se pudo crear el comprobante de demostración.');
        }

        try {
            fwrite($file, $this->receiptPdf($reference, $amount, $date));
            $metadata = stream_get_meta_data($file);
            $path = $metadata['uri'] ?? null;
            if (! is_string($path)) {
                throw new RuntimeException('No se pudo leer el archivo del comprobante de demostración.');
            }

            return $operation(new UploadedFile($path, $reference.'.pdf', 'application/pdf', UPLOAD_ERR_OK, true));
        } finally {
            fclose($file);
        }
    }

    private function receiptPdf(string $reference, string $amount, CarbonImmutable $date): string
    {
        $content = "BT /F1 16 Tf 50 760 Td\n";
        foreach (['FONDO FAMILIAR', 'Comprobante de demostracion', 'Referencia: '.$reference, 'Monto: USD '.$amount, 'Fecha: '.$date->toDateString(), 'Documento ficticio, sin validez bancaria.'] as $line) {
            $content .= '('.str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line).") Tj 0 -28 Td\n";
        }
        $content .= 'ET';
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Length '.strlen($content).">>\nstream\n".$content."\nendstream",
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n".$object."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 6\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf."trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
    }
}
