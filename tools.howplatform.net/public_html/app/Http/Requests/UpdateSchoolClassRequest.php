<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;

class UpdateSchoolClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        // عدّل حسب نظام الصلاحيات لديك إن لزم
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'students'        => $this->normalizeNewlines($this->input('students')),
            'together_rules'  => $this->normalizeNewlines($this->input('together_rules')),
            'separate_rules'  => $this->normalizeNewlines($this->input('separate_rules')),
        ]);
    }

    public function rules(): array
    {
        return [
            'name'            => ['required', 'string', 'max:255'],
            'notes'           => ['nullable', 'string'],
            'students'        => ['required', 'string'],
            'together_rules'  => ['nullable', 'string'],
            'separate_rules'  => ['nullable', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name'           => 'اسم الصف',
            'notes'          => 'ملاحظات',
            'students'       => 'قائمة الطلاب',
            'together_rules' => 'قواعد معًا',
            'separate_rules' => 'قواعد منفصلين',
        ];
    }

    /** @return \Illuminate\Support\Collection<string> */
    public function studentNames(): Collection
    {
        return collect(explode("\n", $this->input('students', '')))
            ->map(fn (string $line) => trim($line))
            ->filter(fn ($v) => $v !== '')
            ->unique()
            ->values();
    }

    /** @return array<int, array{0:string,1:string}> */
    public function togetherPairs(): array
    {
        return $this->parsePairs($this->input('together_rules', ''));
    }

    /** @return array<int, array{0:string,1:string}> */
    public function separatePairs(): array
    {
        return $this->parsePairs($this->input('separate_rules', ''));
    }

    private function normalizeNewlines(?string $text): string
    {
        return str_replace(["\r\n", "\r"], "\n", $text ?? '');
    }

    /** @return array<int, array{0:string,1:string}> */
    private function parsePairs(?string $block): array
    {
        $block = $block ?? '';
        $lines = preg_split('/\n+/', $block, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $pairs = [];

        foreach ($lines as $line) {
            $parts = preg_split('/\s*[,،]\s*/u', trim($line));
            if (is_array($parts) && count($parts) >= 2) {
                $first  = trim((string) $parts[0]);
                $second = trim((string) $parts[1]);
                if ($first !== '' && $second !== '') {
                    $pairs[] = [$first, $second];
                }
            }
        }

        return $pairs;
    }
}
