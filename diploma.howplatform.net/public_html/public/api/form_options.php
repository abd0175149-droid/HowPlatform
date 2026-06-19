<?php
header('Content-Type: application/json');
require_once '../app/Config/Database.php';

use App\Models\UniversityModel;
use App\Models\SpecialtyModel;
use App\Models\AcademicYearModel;

$request = service('request');
$type    = $request->getGet('type');
$id      = (int) $request->getGet('id');

if ($type === 'universities') {
    $builder = \Config\Database::connect()->table('university_diploma_types');
    $builder->select('universities.id, universities.name')
            ->join('universities', 'universities.id = university_diploma_types.university_id')
            ->where('diploma_type_id', $id)
            ->groupBy('universities.id');
    echo json_encode($builder->get()->getResultArray());
}
elseif ($type === 'specialties') {
    $builder = \Config\Database::connect()->table('university_specialties');
    $builder->select('specialties.id, specialties.name')
            ->join('specialties', 'specialties.id = university_specialties.specialty_id')
            ->where('university_id', $id)
            ->groupBy('specialties.id');
    echo json_encode($builder->get()->getResultArray());
}
elseif ($type === 'academic_years') {
    $builder = \Config\Database::connect()->table('university_academic_years');
    $builder->select('academic_years.id, academic_years.name')
            ->join('academic_years', 'academic_years.id = university_academic_years.academic_year_id')
            ->where('university_id', $id)
            ->groupBy('academic_years.id');
    echo json_encode($builder->get()->getResultArray());
}
