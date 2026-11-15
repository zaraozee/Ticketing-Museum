<?php

use App\Support\FingerprintNormalizer;

/*
|--------------------------------------------------------------------------
| Normalisasi sidik jari lintas driver
|--------------------------------------------------------------------------
|
| `db:verify-restore` membandingkan database sumber dengan database sementara
| hasil restore. Di produksi keduanya berbeda driver (PostgreSQL vs SQLite),
| sehingga representasi nilai berbeda meski datanya identik. Tes ini mengunci
| kesetaraan itu agar latihan restore tidak melaporkan kegagalan palsu.
|
*/

it('treats boolean representations from different drivers as equal', function (): void {
    $postgres = FingerprintNormalizer::hash([['id' => 1, 'is_active' => true, 'is_closed' => false]]);
    $sqlite = FingerprintNormalizer::hash([['id' => 1, 'is_active' => 1, 'is_closed' => 0]]);

    expect($postgres)->toBe($sqlite);
});

it('treats numeric strings and floats with the same value as equal', function (): void {
    $postgres = FingerprintNormalizer::hash([['id' => 1, 'latitude' => '-6.2000000', 'amount' => '50000.00']]);
    $sqlite = FingerprintNormalizer::hash([['id' => 1, 'latitude' => -6.2, 'amount' => 50000]]);

    expect($postgres)->toBe($sqlite);
});

it('ignores column order returned by the driver', function (): void {
    $first = FingerprintNormalizer::hash([['id' => 1, 'name' => 'Museum', 'city' => 'Bandung']]);
    $second = FingerprintNormalizer::hash([['city' => 'Bandung', 'name' => 'Museum', 'id' => 1]]);

    expect($first)->toBe($second);
});

it('still detects a real value difference', function (string $left, string $right): void {
    $a = FingerprintNormalizer::hash([['id' => 1, 'name' => $left]]);
    $b = FingerprintNormalizer::hash([['id' => 1, 'name' => $right]]);

    expect($a)->not->toBe($b);
})->with([
    'teks berbeda' => ['Museum A', 'Museum B'],
    'angka berbeda' => ['100', '101'],
    'null vs kosong' => ['', 'x'],
]);

it('keeps null distinct from an empty string', function (): void {
    $null = FingerprintNormalizer::hash([['id' => 1, 'note' => null]]);
    $empty = FingerprintNormalizer::hash([['id' => 1, 'note' => '']]);

    expect($null)->not->toBe($empty);
});

it('normalizes json columns consistently', function (): void {
    $row = ['id' => 1, 'payload' => ['status' => 'paid', 'gross' => 50000]];

    expect(FingerprintNormalizer::hash([$row]))
        ->toBe(FingerprintNormalizer::hash([['payload' => ['status' => 'paid', 'gross' => 50000], 'id' => 1]]));
});
