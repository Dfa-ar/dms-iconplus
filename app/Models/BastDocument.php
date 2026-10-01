<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BastDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'nomor_bast',
        'region_id',
        'kantor_perwakilan_id',
        'created_by',
        'tanggal',
        'status',
        'notes',
        'file_url',
        'pihak_menyerahkan',
        'pihak_menerima',
        'jabatan_menyerahkan',
        'jabatan_menerima',
        'lokasi',
        'void_reason',
        'voided_by',
        'voided_at',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'voided_at' => 'datetime',
    ];

    public static function blueprintTemplateFields(): array
    {
        return [
            'nomor_bast' => 'Nomor BAST',
            'pihak_menyerahkan' => 'Pihak Yang Menyerahkan',
            'pihak_menerima' => 'Pihak Yang Menerima',
            'jabatan_menyerahkan' => 'Jabatan Penyerah',
            'jabatan_menerima' => 'Jabatan Penerima',
            'lokasi' => 'Lokasi',
            'tanggal' => 'Tanggal',
        ];
    }

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function kantorPerwakilan(): BelongsTo
    {
        return $this->belongsTo(KantorPerwakilan::class, 'kantor_perwakilan_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(BastItem::class);
    }

    public function archivedItems(): HasMany
    {
        return $this->hasMany(BastItemArchive::class);
    }

    public function allItems()
    {
        return $this->items()->with('paOrder')->get()
            ->concat($this->archivedItems()->with('paOrder')->get())
            ->sortBy(fn ($item) => [$item->created_at?->getTimestamp() ?? 0, $item->id])
            ->values();
    }

    public function buildPdfContent(): string
    {
        return $this->toPdfText();
    }

    public function toPdfText(): string
    {
        $lines = [
            'BERITA ACARA SERAH TERIMA (BAST)',
            '================================',
            '',
            'Nomor BAST        : ' . ($this->nomor_bast ?? '-'),
            'Tanggal           : ' . ($this->tanggal ?? '-'),
            'Lokasi            : ' . ($this->lokasi ?? $this->region?->kabupaten_kota ?? '-'),
            '',
            'Pihak Yang Menyerahkan',
            'Nama/Jabatan      : ' . ($this->pihak_menyerahkan ?? 'PT Icon Plus'),
            'Jabatan           : ' . ($this->jabatan_menyerahkan ?? 'Manager Operasional'),
            '',
            'Pihak Yang Menerima',
            'Nama/Jabatan      : ' . ($this->pihak_menerima ?? 'Pelanggan'),
            'Jabatan           : ' . ($this->jabatan_menerima ?? 'PIC Pelanggan'),
            '',
            'Daftar PA / Item:',
        ];

        foreach ($this->allItems() as $index => $item) {
            $paOrder = $item->paOrder;
            $lines[] = sprintf('%d. %s | %s | SN: %s', $index + 1, $paOrder?->pa_number ?? '-', $paOrder?->customer_name ?? '-', $item->serial_number ?? '-');
        }

        if (! empty($this->notes)) {
            $lines[] = '';
            $lines[] = 'Catatan: ' . $this->notes;
        }

        if ($this->status === 'VOID') {
            $lines[] = '';
            $lines[] = 'STATUS: VOID';
            $lines[] = 'Alasan pembatalan: ' . ($this->void_reason ?? '-');
        }

        $lines[] = '';
        $lines[] = 'Mengetahui:';
        $lines[] = '1. Pihak Menyerahkan : ' . ($this->pihak_menyerahkan ?? 'PT Icon Plus');
        $lines[] = '2. Pihak Menerima    : ' . ($this->pihak_menerima ?? 'Pelanggan');

        return implode("\n", $lines);
    }
}
