<?php
namespace App\Controllers\Admins;

use CodeIgniter\Controller;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Database\Exceptions\DatabaseException;
use App\Models\TaskTypeModel;

class TaskTypeOptions extends Controller
{
    use ResponseTrait;

    private TaskTypeModel $typeModel;

    public function __construct()
    {
        $this->typeModel = new TaskTypeModel();
    }

    /** صفحة HTML */
    public function index()
    {
        return view('admins/pages/task_type_options', [
            // البيانات الخاصة بالصفحة
            'types'      => $this->typeModel->orderBy('name','ASC')->findAll(),
            'typesCount' => $this->typeModel->countAllResults(),

            // ✅ مفاتيح حماية لتجنب Undefined variable إن وُجدت أسطر قديمة في الواجهة
            'universities'        => [],
            'diplomaOptions'      => [],
            'uniOptions'          => [],
            'specOptions'         => [],
            'years'               => [],
            'yearOptions'         => [],
            'universitiesCount'   => 0,
            'semestersCount'      => 0,
        ]);
    }

    /** API: العداد */
    public function summary()
    {
        return $this->respond(['count' => $this->typeModel->countAllResults()]);
    }

    /** API: إنشاء نوع مهمة */
    public function create()
    {
        $data = $this->request->getJSON(true);

        if (! $this->validateData($data, ['name' => 'required|is_unique[task_types.name]'])) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        try {
            $id = $this->typeModel->insert(['name' => $data['name']], true);
        } catch (DatabaseException $e) {
            if ($e->getCode() === 1062) {
                return $this->respond(['error' => 'اسم نوع المهمّة موجود بالفعل'], 409);
            }
            throw $e;
        }

        return $this->respondCreated(['id' => $id, 'name' => $data['name']]);
    }

    /** API: حذف نوع مهمة */
    public function delete(int $id)
    {
        if (! $this->typeModel->find($id)) {
            return $this->failNotFound('Task-type not found');
        }

        try {
            $this->typeModel->delete($id, true);
        } catch (DatabaseException $e) {
            if ($e->getCode() === 1451) {
                return $this->respond(['error' => 'لا يمكن حذف هذا النوع لأنه مرتبط بمهمات موجودة'], 409);
            }
            throw $e;
        }

        return $this->respondDeleted(['deleted' => true]);
    }
}
