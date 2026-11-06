<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ExaminationIndicatorSchema
{
    private const DEFINITIONS = [
        'blood_pressure' => ['type' => 'text', 'rules' => ['string', 'max:32']],
        'past_disease_history' => ['type' => 'text', 'rules' => ['string', 'max:2000', 'not_regex:/<[^>]*>/']],
        'family_disease_history' => ['type' => 'text', 'rules' => ['string', 'max:2000', 'not_regex:/<[^>]*>/']],
        'personal_hygiene' => ['type' => 'enum', 'values' => ['good', 'fair', 'poor']],
        'breakfast' => ['type' => 'boolean'],
        'smoking' => ['type' => 'boolean'],
        'snacks' => ['type' => 'boolean'],
        'alcohol_narcotics' => ['type' => 'boolean'],
        'height_cm' => ['type' => 'number', 'rules' => ['numeric', 'gt:0', 'max:999.99', 'decimal:0,2']],
        'weight_kg' => ['type' => 'number', 'rules' => ['numeric', 'gt:0', 'max:999.99', 'decimal:0,2']],
        'nutrition_status' => ['type' => 'enum', 'values' => ['very_thin', 'thin', 'normal', 'overweight', 'obesity']],
        'anemia' => ['type' => 'boolean'],
        'eyes_external' => ['type' => 'enum', 'values' => ['healthy', 'unhealthy']],
        'color_blindness' => ['type' => 'boolean'],
        'uses_glasses' => ['type' => 'boolean'],
        'ears_external' => ['type' => 'enum', 'values' => ['healthy', 'infection', 'cerumen']],
        'hearing' => ['type' => 'enum', 'values' => ['normal', 'impaired']],
        'cleft_lip_palate' => ['type' => 'boolean'],
        'mouth_corner_lesion' => ['type' => 'boolean'],
        'mouth_ulcer' => ['type' => 'boolean'],
        'dirty_tongue' => ['type' => 'boolean'],
        'other_lesion' => ['type' => 'boolean'],
        'dental_caries' => ['type' => 'boolean'],
        'bleeding_gums' => ['type' => 'boolean'],
        'swollen_gums' => ['type' => 'boolean'],
        'dental_plaque' => ['type' => 'boolean'],
        'tartar' => ['type' => 'boolean'],
        'irregular_front_teeth' => ['type' => 'boolean'],
    ];

    public function keys(): array
    {
        return array_keys(self::DEFINITIONS);
    }

    public function validate(array $observations): array
    {
        $validated = [];
        $errors = [];

        foreach ($observations as $key => $observation) {
            if (! is_string($key) || ! isset(self::DEFINITIONS[$key])) {
                $errors["observations.$key"][] = 'Indikator tidak dikenal.';

                continue;
            }
            if (! is_array($observation)) {
                $errors["observations.$key"][] = 'Indikator harus berupa objek status dan nilai.';

                continue;
            }

            $status = $observation['status'] ?? null;
            if (! in_array($status, ['recorded', 'not_examined'], true)) {
                $errors["observations.$key.status"][] = 'Status harus recorded atau not_examined.';

                continue;
            }
            if ($status === 'not_examined') {
                if (array_key_exists('value', $observation) && $observation['value'] !== null) {
                    $errors["observations.$key.value"][] = 'Nilai harus kosong bila indikator tidak diperiksa.';

                    continue;
                }
                $validated[$key] = ['status' => 'not_examined', 'value' => null];

                continue;
            }

            if (! array_key_exists('value', $observation) || $observation['value'] === null) {
                $errors["observations.$key.value"][] = 'Nilai wajib diisi untuk status recorded.';

                continue;
            }
            $value = $observation['value'];
            $definition = self::DEFINITIONS[$key];
            $rules = match ($definition['type']) {
                'boolean' => ['required', 'boolean'],
                'enum' => ['required', Rule::in($definition['values'])],
                default => array_merge(['required'], $definition['rules']),
            };
            $validator = Validator::make(['value' => $value], ['value' => $rules]);
            if ($validator->fails()) {
                $errors["observations.$key.value"] = $validator->errors()->all();

                continue;
            }
            if ($definition['type'] === 'boolean') {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            } elseif ($definition['type'] === 'number') {
                $value = number_format((float) $value, 2, '.', '');
            }

            $validated[$key] = ['status' => 'recorded', 'value' => $value];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $validated;
    }

    public function missing(array $savedObservations): array
    {
        return array_values(array_diff($this->keys(), array_keys($savedObservations)));
    }
}
