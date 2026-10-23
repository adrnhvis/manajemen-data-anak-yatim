<?php

namespace Database\Seeders\Support;

/**
 * Generator riwayat workflow SIMULASI (tanpa Eloquent, jadi bisa diuji sendiri).
 *
 * Bukan data nyata: parameter di bawah adalah ASUMSI untuk menguji alur K-Means,
 * bukan temuan tentang wilayah mana pun. Setiap wilayah diberi profil laten
 * (lancar / sedang / pendampingan) plus faktor acak per wilayah supaya kelompoknya
 * saling tumpang tindih seperti data sungguhan, bukan terpisah rapi.
 */
final class SimulatedWorkflow
{
    /** returns = rata-rata revisi per pengajuan; resubmit/first = rata-rata hari; reject = peluang ditolak. */
    public const PROFILES = [
        'lancar' => ['returns' => 0.15, 'resubmit' => 3.0, 'first' => 6.0, 'reject' => 0.02],
        'sedang' => ['returns' => 0.90, 'resubmit' => 8.0, 'first' => 12.0, 'reject' => 0.08],
        'pendampingan' => ['returns' => 2.00, 'resubmit' => 18.0, 'first' => 24.0, 'reject' => 0.20],
    ];

    private const WEIGHTS = ['lancar' => 55, 'sedang' => 30, 'pendampingan' => 15];

    private const DAY = 86400;

    public static function pickProfile(): string
    {
        $roll = mt_rand(1, array_sum(self::WEIGHTS));
        foreach (self::WEIGHTS as $profile => $weight) {
            if (($roll -= $weight) <= 0) {
                return $profile;
            }
        }

        return 'lancar';
    }

    /** Pengali per wilayah (lognormal, rata-rata ~1) agar profil saling tumpang tindih. */
    public static function villageFactor(): float
    {
        return exp(self::normal() * 0.25 - 0.03125);
    }

    /** Jumlah pengajuan per wilayah: sebagian belum pernah mengajukan, sebagian terlalu sedikit. */
    public static function applicationCount(): int
    {
        $roll = mt_rand(1, 100);

        return match (true) {
            $roll <= 12 => 0,
            $roll <= 32 => mt_rand(1, 4),
            default => mt_rand(5, 14),
        };
    }

    /**
     * Riwayat satu pengajuan dari draft sampai keadaannya saat ini.
     *
     * @return array{events: list<array{action: string, from: string, to: string, at: int, note: ?string}>, status: string}
     */
    public static function events(string $profile, float $factor, int $createdAt, int $now): array
    {
        $p = self::PROFILES[$profile];
        $events = [];

        $t = $createdAt + (int) (self::duration($p['first'] * $factor) * self::DAY);
        $events[] = ['action' => 'submit', 'from' => 'draft', 'to' => 'submitted', 'at' => $t, 'note' => null];

        for ($i = self::poisson($p['returns'] * $factor); $i > 0; $i--) {
            $t += mt_rand(12, 72) * 3600;
            $events[] = ['action' => 'request_revision', 'from' => 'submitted', 'to' => 'revision_required', 'at' => $t, 'note' => 'Simulasi: data perlu diperbaiki'];
            $t += (int) (self::duration($p['resubmit'] * $factor) * self::DAY);
            $events[] = ['action' => 'submit', 'from' => 'revision_required', 'to' => 'submitted', 'at' => $t, 'note' => null];
        }

        if (self::uniform() < $p['reject']) {
            $t += mt_rand(1, 4) * self::DAY;
            $events[] = ['action' => 'reject', 'from' => 'submitted', 'to' => 'rejected', 'at' => $t, 'note' => 'Simulasi: tidak memenuhi syarat'];
        } else {
            $t += mt_rand(1, 4) * self::DAY;
            $events[] = ['action' => 'validate', 'from' => 'submitted', 'to' => 'validated', 'at' => $t, 'note' => null];
            if (self::uniform() < 0.85) {
                $t += mt_rand(1, 5) * self::DAY;
                $events[] = ['action' => 'verify', 'from' => 'validated', 'to' => 'verified', 'at' => $t, 'note' => null];
            }
        }

        // Kejadian di masa depan belum terjadi: potong sampai "sekarang".
        $events = array_values(array_filter($events, fn (array $e) => $e['at'] <= $now));

        return ['events' => $events, 'status' => $events === [] ? 'draft' : end($events)['to']];
    }

    public static function uniform(): float
    {
        return mt_rand() / mt_getrandmax();
    }

    /** Distribusi normal baku (Box-Muller). */
    public static function normal(): float
    {
        return sqrt(-2 * log(max(self::uniform(), 1e-12))) * cos(2 * M_PI * self::uniform());
    }

    /** Durasi lognormal dengan rata-rata ~$mean hari (minimal 0,1 hari). */
    private static function duration(float $mean): float
    {
        return max(0.1, $mean * exp(self::normal() * 0.5 - 0.125));
    }

    private static function poisson(float $lambda): int
    {
        $limit = exp(-$lambda);
        $k = 0;
        $p = 1.0;
        do {
            $k++;
            $p *= self::uniform();
        } while ($p > $limit);

        return $k - 1;
    }
}
