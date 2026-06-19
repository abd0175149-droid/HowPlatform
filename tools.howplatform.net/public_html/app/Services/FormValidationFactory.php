<?php
namespace App\Services;
class FormValidationFactory {
  public function make(array $dataSchema): array {
    $rules=[]; $names=[];
    foreach (($dataSchema['fields'] ?? []) as $f){
      $rules[$f['name']] = $f['rules'] ?? '';
      $names[$f['name']] = $f['label'] ?? $f['name'];
    }
    return [$rules, $names];
  }
}
