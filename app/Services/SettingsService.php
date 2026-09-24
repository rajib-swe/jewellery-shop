<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class SettingsService
{
    /**
     * @return array{shop_name: string, shop_address: string, shop_phone: string, shop_logo: ?string, vat_percentage: string, currency_symbol: string, weight_unit: string, default_pawn_interest_rate: string, invoice_footer: string}
     */
    public static function defaults(): array
    {
        return [
            'shop_name' => 'Jewellery Shop',
            'shop_address' => '',
            'shop_phone' => '',
            'shop_logo' => null,
            'vat_percentage' => '0.00',
            'currency_symbol' => '৳',
            'weight_unit' => 'gram',
            'default_pawn_interest_rate' => '0.00',
            'invoice_footer' => '',
        ];
    }

    /**
     * @return array{shop_name: string, shop_address: string, shop_phone: string, shop_logo: ?string, vat_percentage: string, currency_symbol: string, weight_unit: string, default_pawn_interest_rate: string, invoice_footer: string}
     */
    public function all(): array
    {
        $stored = Setting::query()->pluck('value', 'key');
        $defaults = self::defaults();

        return [
            'shop_name' => (string) ($stored->get('shop_name', $defaults['shop_name'])),
            'shop_address' => (string) ($stored->get('shop_address', $defaults['shop_address'])),
            'shop_phone' => (string) ($stored->get('shop_phone', $defaults['shop_phone'])),
            'shop_logo' => $stored->get('shop_logo') ?: null,
            'vat_percentage' => (string) ($stored->get('vat_percentage', $defaults['vat_percentage'])),
            'currency_symbol' => (string) ($stored->get('currency_symbol', $defaults['currency_symbol'])),
            'weight_unit' => (string) ($stored->get('weight_unit', $defaults['weight_unit'])),
            'default_pawn_interest_rate' => (string) ($stored->get('default_pawn_interest_rate', $defaults['default_pawn_interest_rate'])),
            'invoice_footer' => (string) ($stored->get('invoice_footer', $defaults['invoice_footer'])),
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array{shop_name: string, shop_address: string, shop_phone: string, shop_logo: ?string, vat_percentage: string, currency_symbol: string, weight_unit: string, default_pawn_interest_rate: string, invoice_footer: string}
     */
    public function update(array $values, ?UploadedFile $logo = null, bool $removeLogo = false): array
    {
        $values = array_intersect_key($values, array_flip(array_keys(self::defaults())));
        unset($values['shop_logo']);

        $oldLogo = Setting::query()->where('key', 'shop_logo')->value('value');
        $storedLogo = null;

        if ($logo !== null) {
            $storedLogo = $logo->store('settings/logos', 'public');

            if ($storedLogo === false) {
                throw new RuntimeException('Unable to store the shop logo.');
            }
        }

        $replacementLogo = $oldLogo;

        if ($logo !== null || $removeLogo) {
            $replacementLogo = $storedLogo;
            $values['shop_logo'] = $storedLogo;
        }

        try {
            DB::transaction(function () use ($values): void {
                foreach ($values as $key => $value) {
                    Setting::query()->updateOrCreate(
                        ['key' => $key],
                        ['value' => $this->normalizeValue($key, $value)],
                    );
                }
            });
        } catch (Throwable $exception) {
            if ($storedLogo !== null) {
                Storage::disk('public')->delete($storedLogo);
            }

            throw $exception;
        }

        if ($oldLogo && $oldLogo !== $replacementLogo) {
            Storage::disk('public')->delete($oldLogo);
        }

        return $this->all();
    }

    private function normalizeValue(string $key, mixed $value): string
    {
        if (in_array($key, ['vat_percentage', 'default_pawn_interest_rate'], true)) {
            return number_format((float) $value, 2, '.', '');
        }

        return $value === null ? '' : (string) $value;
    }
}
