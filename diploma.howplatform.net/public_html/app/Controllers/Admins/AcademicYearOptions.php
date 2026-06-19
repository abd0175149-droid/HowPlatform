<?php

namespace App\Controllers\Admins;

use App\Controllers\BaseController;
use App\Models\AcademicYearModel;
use App\Models\SemesterModel;
use App\Models\UniversityModel;
use App\Models\UniversityAcademicYearModel;
use DateTimeImmutable;

/**
 * يعرض صفحة إدارة الأعوام والفصول داخل لوحة التحكم
 * المسار في Routes.php:
 *   $routes->get('admin/pages/academic_year_options', 'AcademicYearOptions::index');
 */
class AcademicYearOptions extends BaseController
{
    /* النماذج */
    protected AcademicYearModel           $yearModel;
    protected SemesterModel               $semesterModel;
    protected UniversityModel             $universityModel;
    protected UniversityAcademicYearModel $uaModel;

    public function __construct()
    {
        $this->yearModel       = new AcademicYearModel();
        $this->semesterModel   = new SemesterModel();
        $this->universityModel = new UniversityModel();
        $this->uaModel         = new UniversityAcademicYearModel();
    }

    /** صفحة HTML */
    public function index()
    {
        /** مصفوفة البيانات للـ View */
        $data = [
            'yearsCount'        => $this->yearModel->countAllResults(),
            'semestersCount'    => $this->semesterModel->countAllResults(),
            'universitiesCount' => $this->universityModel->countAllResults(),
            /* خيارات الاختيار */
            'yearOptions'       => $this->yearModel->orderBy('label', 'DESC')->findAll(),
            'universityOptions' => $this->universityModel->orderBy('name', 'ASC')->findAll(),
        ];

        /* الأعوام + الفصول + الجامعات */
        $today = new DateTimeImmutable('today');
        $data['years'] = $this->yearModel
            ->orderBy('start_date', 'DESC')
            ->findAll();

        foreach ($data['years'] as &$year) {
            /* فصول العام */
            $year['semesters'] = $this->semesterModel
                ->where('academic_year_id', $year['id'])
                ->findAll();

            /* الجامعات المرتبطة */
            $year['universities'] = $this->universityModel
                ->select('universities.id, universities.name')
                ->join(
                    'university_academic_years',
                    'universities.id = university_academic_years.university_id'
                )
                ->where('university_academic_years.academic_year_id', $year['id'])
                ->findAll();

            /* حالة العام ونسبة التقدم */
            $start = new DateTimeImmutable($year['start_date']);
            $end   = new DateTimeImmutable($year['end_date']);

            if ($today < $start) {
                $year['status']   = 'upcoming';
                $year['progress'] = 0;
            } elseif ($today > $end) {
                $year['status']   = 'ended';
                $year['progress'] = 100;
            } else {
                $totalDays        = max($start->diff($end)->days, 1);
                $passed           = $start->diff($today)->days;
                $year['status']   = 'active';
                $year['progress'] = (int) round(($passed / $totalDays) * 100);
            }
        }
        unset($year);

        return view('admins/pages/academic_year_options', $data);
    }
}
